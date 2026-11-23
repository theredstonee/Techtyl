import React, { useCallback, useEffect, useRef, useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/billing';
import {
    cancelSubscription,
    day,
    dayTime,
    errorText,
    goToStripe,
    loadOverview,
    money,
    openPortal,
    Overview,
    Package,
    placeOrder,
    resumeSubscription,
    size,
    startTopup,
    Subscription,
} from '../../billing/api';
import '../../billing/styles.css';

type Notice = { tone: 'good' | 'warn' | 'bad'; text: string } | null;
type Translate = (key: string, replace?: Record<string, string | number>) => string;

interface Shared {
    overview: Overview;
    t: Translate;
    locale: string;
    onError: (text: string) => void;
}

const motionLevel = (): string => {
    if (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return 'aus';
    }

    return document.documentElement.getAttribute('data-techtyl-motion') || 'dezent';
};

/** Counts an amount smoothly towards its new value (full motion only). */
const useCountUp = (goal: number): number => {
    const [shown, setShown] = useState(goal);
    const from = useRef(goal);

    useEffect(() => {
        if (motionLevel() !== 'voll' || from.current === goal) {
            from.current = goal;
            setShown(goal);

            return;
        }
        const start = from.current;
        const began = performance.now();
        let frame = 0;
        const step = (now: number) => {
            const progress = Math.min(1, (now - began) / 700);
            const eased = 1 - Math.pow(1 - progress, 3);
            setShown(Math.round(start + (goal - start) * eased));
            if (progress < 1) {
                frame = requestAnimationFrame(step);
            } else {
                from.current = goal;
            }
        };
        frame = requestAnimationFrame(step);

        return () => {
            cancelAnimationFrame(frame);
            from.current = goal;
        };
    }, [goal]);

    return shown;
};

const Legal = ({ overview, t }: { overview: Overview; t: Translate }) => {
    const links = [
        { label: t('terms'), href: overview.terms_url },
        { label: t('withdrawal'), href: overview.withdrawal_url },
    ].filter((link) => /^https?:\/\//i.test(link.href));
    if (links.length === 0) {
        return null;
    }

    return (
        <p className={'techtyl-billing-legal'}>
            {t('legal_intro')}{' '}
            {links.map((link, index) => (
                <React.Fragment key={link.label}>
                    {index > 0 && ` ${t('legal_and')} `}
                    <a href={link.href} target={'_blank'} rel={'noopener noreferrer'}>
                        {link.label}
                    </a>
                </React.Fragment>
            ))}
            .
        </p>
    );
};

// ---------------------------------------------------------------------------------------------

const BalancePanel = ({ overview, t, locale, onError }: Shared) => {
    const amounts = overview.topup_amounts;
    const [amount, setAmount] = useState<number>(amounts.length > 1 ? amounts[1] : amounts[0] || 0);
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);
    const shown = useCountUp(overview.balance);
    const cash = (cents: number) => money(cents, overview.currency, locale);

    let runway = t('runway_none');
    if (overview.days_left !== null) {
        runway =
            overview.days_left <= 0
                ? t('runway_empty')
                : t(overview.days_left === 1 ? 'runway_one' : 'runway_many', { days: overview.days_left, cost: cash(overview.daily_cost) });
    }

    const pay = () => {
        setBusy(true);
        startTopup(amount)
            .then(goToStripe)
            .catch((error) => {
                setBusy(false);
                onError(errorText(error, t));
            });
    };

    return (
        <section className={'techtyl-billing-panel techtyl-billing-balance'} aria-labelledby={'techtyl-billing-balance-title'}>
            <h2 id={'techtyl-billing-balance-title'}>{t('balance')}</h2>
            <p className={'techtyl-billing-big'} aria-live={'polite'}>
                {cash(shown)}
            </p>
            <p className={`techtyl-billing-muted${overview.days_left !== null && overview.days_left <= 2 ? ' techtyl-billing-warn-text' : ''}`}>{runway}</p>

            {!overview.ready ? (
                <p className={'techtyl-billing-hint'}>{t('topup_not_ready')}</p>
            ) : amounts.length === 0 ? (
                <p className={'techtyl-billing-hint'}>{t('topup_no_amounts')}</p>
            ) : !confirming ? (
                <div className={'techtyl-billing-topup'}>
                    <div className={'techtyl-billing-seg'} role={'group'} aria-label={t('choose_amount')}>
                        {amounts.map((value) => (
                            <button key={value} type={'button'} aria-pressed={value === amount} onClick={() => setAmount(value)}>
                                {cash(value)}
                            </button>
                        ))}
                    </div>
                    <button type={'button'} className={'techtyl-billing-btn techtyl-billing-go'} onClick={() => setConfirming(true)}>
                        {t('topup')}
                    </button>
                </div>
            ) : (
                <div className={'techtyl-billing-confirm'} role={'group'} aria-label={t('topup_confirm_label')}>
                    <p>
                        {t('topup_summary_before')} <strong>{cash(amount)}</strong> {t('topup_summary_after')}
                    </p>
                    <Legal overview={overview} t={t} />
                    <div className={'techtyl-billing-actions'}>
                        <button type={'button'} className={'techtyl-billing-btn'} disabled={busy} onClick={() => setConfirming(false)}>
                            {t('back')}
                        </button>
                        <button type={'button'} className={'techtyl-billing-btn techtyl-billing-go'} disabled={busy} onClick={pay}>
                            {busy ? t('to_stripe') : t('topup_pay', { amount: cash(amount) })}
                        </button>
                    </div>
                </div>
            )}
        </section>
    );
};

// ---------------------------------------------------------------------------------------------

const statusOf = (subscription: Subscription): { key: string; tone: string } => {
    if (subscription.provision === 'failed' && subscription.status === 'active') {
        return { key: 'st_provision_failed', tone: 'bad' };
    }
    if (subscription.status === 'pending') {
        return { key: 'st_pending', tone: 'wait' };
    }
    if (subscription.status === 'past_due') {
        return { key: subscription.suspended ? 'st_past_due_suspended' : 'st_past_due', tone: 'bad' };
    }
    if (subscription.status === 'canceled') {
        return { key: 'st_canceled', tone: 'off' };
    }
    if (subscription.status === 'ended') {
        return { key: 'st_ended', tone: 'off' };
    }
    if (subscription.provision !== 'done') {
        return { key: 'st_setting_up', tone: 'wait' };
    }
    if (subscription.cancel_at_period_end) {
        return { key: 'st_cancelled', tone: 'wait' };
    }

    return { key: 'st_active', tone: 'good' };
};

const nextPayment = (subscription: Subscription, t: Translate, locale: string): string => {
    const price = money(subscription.price, subscription.currency, locale);
    if (subscription.status === 'canceled' || subscription.status === 'ended') {
        return subscription.delete_on ? t('next_delete', { date: day(subscription.delete_on, locale) }) : '–';
    }
    if (subscription.kind === 'balance') {
        return t('per_day', { price });
    }
    if (subscription.status === 'pending') {
        return t('per_month', { price });
    }
    if (subscription.cancel_at_period_end) {
        return subscription.current_period_end ? t('runs_until', { date: day(subscription.current_period_end, locale) }) : t('runs_out');
    }

    return subscription.current_period_end ? `${day(subscription.current_period_end, locale)} · ${price}` : t('per_month', { price });
};

const SubscriptionsPanel = ({ overview, t, locale, onError, onChanged }: Shared & { onChanged: () => void }) => {
    const [asking, setAsking] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const subscriptions = overview.subscriptions;

    const run = (action: Promise<unknown>) => {
        setBusy(true);
        action
            .then(() => {
                setAsking(null);
                onChanged();
            })
            .catch((error) => onError(errorText(error, t)))
            .then(() => setBusy(false));
    };

    const portal = () => {
        setBusy(true);
        openPortal()
            .then(goToStripe)
            .catch((error) => {
                setBusy(false);
                onError(errorText(error, t));
            });
    };

    const askText = (subscription: Subscription): string => {
        if (subscription.kind === 'balance') {
            return t('ask_balance', { days: overview.delete_days, server: subscription.server_name });
        }

        return subscription.current_period_end
            ? t('ask_stripe_date', { days: overview.delete_days, date: day(subscription.current_period_end, locale), server: subscription.server_name })
            : t('ask_stripe', { days: overview.delete_days, server: subscription.server_name });
    };

    return (
        <section className={'techtyl-billing-panel'} aria-labelledby={'techtyl-billing-subs-title'}>
            <div className={'techtyl-billing-head'}>
                <h2 id={'techtyl-billing-subs-title'}>{t('subs_title')}</h2>
                {overview.has_customer && overview.ready && (
                    <button type={'button'} className={'techtyl-billing-btn techtyl-billing-sm'} disabled={busy} onClick={portal}>
                        {t('portal')}
                    </button>
                )}
            </div>

            {subscriptions.length === 0 ? (
                <div className={'techtyl-billing-empty'}>
                    <p>{t('subs_empty')}</p>
                    <p className={'techtyl-billing-muted'}>{t('subs_empty_hint')}</p>
                </div>
            ) : (
                <div className={'techtyl-billing-tablewrap'}>
                    <table className={'techtyl-billing-table'}>
                        <thead>
                            <tr>
                                <th scope={'col'}>{t('col_server')}</th>
                                <th scope={'col'}>{t('col_package')}</th>
                                <th scope={'col'}>{t('col_next')}</th>
                                <th scope={'col'}>{t('col_status')}</th>
                                <th scope={'col'}>
                                    <span className={'techtyl-billing-sr'}>{t('col_action')}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.map((subscription) => {
                                const status = statusOf(subscription);
                                const running = subscription.status === 'active' || subscription.status === 'past_due';
                                const canResume = subscription.kind === 'stripe' && subscription.status === 'active' && subscription.cancel_at_period_end;
                                const canCancel = running && !subscription.cancel_at_period_end;

                                return (
                                    <React.Fragment key={subscription.id}>
                                        <tr>
                                            <td>
                                                {subscription.server_identifier && !subscription.suspended ? (
                                                    <a href={`/server/${subscription.server_identifier}`}>{subscription.server_name}</a>
                                                ) : (
                                                    subscription.server_name
                                                )}
                                            </td>
                                            <td>
                                                {subscription.package}
                                                <span className={'techtyl-billing-kind'}>{t(subscription.kind === 'stripe' ? 'kind_stripe' : 'kind_balance')}</span>
                                            </td>
                                            <td className={'techtyl-billing-num'}>{nextPayment(subscription, t, locale)}</td>
                                            <td>
                                                <span className={`techtyl-billing-state techtyl-billing-state-${status.tone}`}>{t(status.key)}</span>
                                            </td>
                                            <td className={'techtyl-billing-cell-action'}>
                                                {canCancel && (
                                                    <button
                                                        type={'button'}
                                                        className={'techtyl-billing-btn techtyl-billing-sm'}
                                                        aria-expanded={asking === subscription.id}
                                                        onClick={() => setAsking(asking === subscription.id ? null : subscription.id)}
                                                    >
                                                        {t('cancel')}
                                                    </button>
                                                )}
                                                {canResume && (
                                                    <button
                                                        type={'button'}
                                                        className={'techtyl-billing-btn techtyl-billing-sm'}
                                                        disabled={busy}
                                                        onClick={() => run(resumeSubscription(subscription.id))}
                                                    >
                                                        {t('resume')}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                        {subscription.provision === 'failed' && subscription.status === 'active' && (
                                            <tr className={'techtyl-billing-sub'}>
                                                <td colSpan={5}>{t(subscription.kind === 'balance' ? 'failed_row_balance' : 'failed_row')}</td>
                                            </tr>
                                        )}
                                        {subscription.status === 'past_due' && (
                                            <tr className={'techtyl-billing-sub'}>
                                                <td colSpan={5}>
                                                    {t(subscription.kind === 'balance' ? 'due_balance' : 'due_stripe')}
                                                    {subscription.suspend_on && ` ${t('due_suspend_on', { date: day(subscription.suspend_on, locale) })}`}
                                                    {subscription.delete_on && ` ${t('due_delete_on', { date: day(subscription.delete_on, locale) })}`}
                                                </td>
                                            </tr>
                                        )}
                                        {asking === subscription.id && canCancel && (
                                            <tr className={'techtyl-billing-sub techtyl-billing-ask'}>
                                                <td colSpan={5}>
                                                    <p>{askText(subscription)}</p>
                                                    <div className={'techtyl-billing-actions'}>
                                                        <button type={'button'} className={'techtyl-billing-btn techtyl-billing-sm'} disabled={busy} onClick={() => setAsking(null)}>
                                                            {t('keep')}
                                                        </button>
                                                        <button
                                                            type={'button'}
                                                            className={'techtyl-billing-btn techtyl-billing-sm techtyl-billing-danger'}
                                                            disabled={busy}
                                                            onClick={() => run(cancelSubscription(subscription.id))}
                                                        >
                                                            {busy ? t('cancelling') : t('cancel_now')}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
};

// ---------------------------------------------------------------------------------------------

const OrderPanel = ({ overview, t, locale, onError, onDone }: Shared & { onDone: (text: string) => void }) => {
    const [picked, setPicked] = useState<Package | null>(null);
    const [serverName, setServerName] = useState('');
    const [eggId, setEggId] = useState<number>(0);
    const [method, setMethod] = useState<'stripe' | 'balance'>('stripe');
    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [agreed, setAgreed] = useState(false);
    const [busy, setBusy] = useState(false);
    const detailRef = useRef<HTMLDivElement>(null);
    const cash = (cents: number) => money(cents, overview.currency, locale);

    const pick = (item: Package) => {
        setPicked(item);
        setEggId(item.eggs[0] ? item.eggs[0].id : 0);
        setMethod(overview.ready || item.price_day === null ? 'stripe' : 'balance');
        setAgreed(false);
        setStep(2);
    };

    useEffect(() => {
        if (step > 1 && detailRef.current) {
            detailRef.current.focus();
        }
    }, [step, picked]);

    if (overview.packages.length === 0) {
        return (
            <section className={'techtyl-billing-panel'} aria-labelledby={'techtyl-billing-order-title'}>
                <h2 id={'techtyl-billing-order-title'}>{t('order_title')}</h2>
                <div className={'techtyl-billing-empty'}>
                    <p>{t('no_packages')}</p>
                    <p className={'techtyl-billing-muted'}>{t('no_packages_hint')}</p>
                </div>
            </section>
        );
    }

    const egg = picked ? picked.eggs.find((item) => item.id === eggId) : undefined;
    const trimmed = serverName.trim();
    const nameValid = trimmed.length >= 1 && trimmed.length <= 60 && !/[<>]/.test(trimmed);
    const needed = picked && picked.price_day !== null ? Math.max(overview.min_balance, picked.price_day) : 0;
    const balanceShort = method === 'balance' && overview.balance < needed;
    const stripeBlocked = method === 'stripe' && !overview.ready;

    const submit = () => {
        if (!picked || !egg) {
            return;
        }
        setBusy(true);
        placeOrder({ package_id: picked.id, egg_id: egg.id, server_name: trimmed, method })
            .then((result) => {
                if (result.url) {
                    goToStripe(result.url);

                    return;
                }
                setBusy(false);
                setStep(1);
                setPicked(null);
                setServerName('');
                onDone(t(result.subscription && result.subscription.provision === 'done' ? 'ordered_ok' : 'ordered_failed', { server: trimmed }));
            })
            .catch((error) => {
                setBusy(false);
                onError(errorText(error, t));
            });
    };

    const specOf = (item: Package) => t('spec', { memory: size(item.memory, locale), cpu: item.cpu, disk: size(item.disk, locale) });

    return (
        <section className={'techtyl-billing-panel'} aria-labelledby={'techtyl-billing-order-title'}>
            <div className={'techtyl-billing-head'}>
                <h2 id={'techtyl-billing-order-title'}>{t('order_title')}</h2>
                <ol className={'techtyl-billing-steps'} aria-label={t('steps')}>
                    {['step_package', 'step_server', 'step_review'].map((key, index) => (
                        <li key={key} aria-current={step === index + 1 ? 'step' : undefined} data-done={step > index + 1 ? 'true' : undefined}>
                            <span>{index + 1}</span>
                            {t(key)}
                        </li>
                    ))}
                </ol>
            </div>

            <div className={'techtyl-billing-plans'} role={'group'} aria-label={t('choose_package')}>
                {overview.packages.map((item) => (
                    <button
                        key={item.id}
                        type={'button'}
                        className={`techtyl-billing-plan${picked && picked.id === item.id ? ' techtyl-billing-pick' : ''}`}
                        aria-pressed={!!picked && picked.id === item.id}
                        onClick={() => pick(item)}
                    >
                        <h3>{item.title}</h3>
                        <p className={'techtyl-billing-muted'}>{specOf(item)}</p>
                        {item.description !== '' && <p className={'techtyl-billing-desc'}>{item.description}</p>}
                        <p className={'techtyl-billing-price'}>{cash(item.price_month)}</p>
                        <p className={'techtyl-billing-muted'}>
                            {item.price_day !== null ? t('price_month_or_day', { price: cash(item.price_day) }) : t('price_month_only')}
                        </p>
                    </button>
                ))}
            </div>

            {picked && step === 2 && (
                <div className={'techtyl-billing-detail'} ref={detailRef} tabIndex={-1} aria-label={t('setup_label')}>
                    <div className={'techtyl-billing-fields'}>
                        <label className={'techtyl-billing-field'}>
                            <span>{t('server_name')}</span>
                            <input
                                type={'text'}
                                maxLength={60}
                                value={serverName}
                                placeholder={t('server_name_placeholder')}
                                onChange={(event) => setServerName(event.target.value)}
                            />
                        </label>
                        <label className={'techtyl-billing-field'}>
                            <span>{t('server_type')}</span>
                            <select value={eggId} onChange={(event) => setEggId(Number(event.target.value))} disabled={picked.eggs.length === 1}>
                                {picked.eggs.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.group} – {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <div className={'techtyl-billing-field'}>
                        <span id={'techtyl-billing-method'}>{t('pay_with')}</span>
                        <div className={'techtyl-billing-methods'} role={'group'} aria-labelledby={'techtyl-billing-method'}>
                            <button type={'button'} aria-pressed={method === 'stripe'} onClick={() => setMethod('stripe')}>
                                <strong>{t('method_stripe')}</strong>
                                <span>{t('method_stripe_hint', { price: cash(picked.price_month) })}</span>
                            </button>
                            {picked.price_day !== null && (
                                <button type={'button'} aria-pressed={method === 'balance'} onClick={() => setMethod('balance')}>
                                    <strong>{t('method_balance')}</strong>
                                    <span>{t('method_balance_hint', { price: cash(picked.price_day) })}</span>
                                </button>
                            )}
                        </div>
                    </div>

                    {stripeBlocked && <p className={'techtyl-billing-hint'}>{t('stripe_blocked')}</p>}
                    {balanceShort && <p className={'techtyl-billing-hint'}>{t('balance_short', { needed: cash(needed), have: cash(overview.balance) })}</p>}

                    <div className={'techtyl-billing-actions'}>
                        <button
                            type={'button'}
                            className={'techtyl-billing-btn'}
                            onClick={() => {
                                setPicked(null);
                                setStep(1);
                            }}
                        >
                            {t('abort')}
                        </button>
                        <button
                            type={'button'}
                            className={'techtyl-billing-btn techtyl-billing-go'}
                            disabled={!nameValid || !egg || balanceShort || stripeBlocked}
                            onClick={() => setStep(3)}
                        >
                            {t('to_summary')}
                        </button>
                    </div>
                </div>
            )}

            {picked && egg && step === 3 && (
                <div className={'techtyl-billing-detail'} ref={detailRef} tabIndex={-1} aria-label={t('review_label')}>
                    <dl className={'techtyl-billing-summary'}>
                        <div>
                            <dt>{t('sum_server')}</dt>
                            <dd>{trimmed}</dd>
                        </div>
                        <div>
                            <dt>{t('sum_type')}</dt>
                            <dd>{egg.label}</dd>
                        </div>
                        <div>
                            <dt>{t('sum_package')}</dt>
                            <dd>
                                {t('sum_spec', { memory: size(picked.memory, locale), cpu: picked.cpu, disk: size(picked.disk, locale), title: picked.title })}
                                {picked.databases > 0 ? t('sum_databases', { count: picked.databases }) : ''}
                                {picked.backups > 0 ? t('sum_backups', { count: picked.backups }) : ''}
                            </dd>
                        </div>
                        <div className={'techtyl-billing-total'}>
                            <dt>{t('sum_price')}</dt>
                            <dd>
                                {method === 'stripe' ? t('per_month', { price: cash(picked.price_month) }) : t('per_day', { price: cash(picked.price_day || 0) })}
                            </dd>
                        </div>
                    </dl>
                    <p className={'techtyl-billing-muted'}>
                        {method === 'stripe' ? t('note_stripe') : t('note_balance', { balance: cash(overview.balance), days: overview.delete_days })}
                    </p>
                    <Legal overview={overview} t={t} />
                    <label className={'techtyl-billing-check'}>
                        <input type={'checkbox'} checked={agreed} onChange={(event) => setAgreed(event.target.checked)} />
                        <span>{t('agree')}</span>
                    </label>
                    <div className={'techtyl-billing-actions'}>
                        <button type={'button'} className={'techtyl-billing-btn'} disabled={busy} onClick={() => setStep(2)}>
                            {t('back')}
                        </button>
                        <button type={'button'} className={'techtyl-billing-btn techtyl-billing-go'} disabled={busy || !agreed} onClick={submit}>
                            {busy ? t('wait') : t(method === 'stripe' ? 'order_stripe' : 'order_balance')}
                        </button>
                    </div>
                </div>
            )}
        </section>
    );
};

// ---------------------------------------------------------------------------------------------

const REASONS = ['topup', 'daily', 'admin', 'refund'];

const LedgerPanel = ({ overview, t, locale }: { overview: Overview; t: Translate; locale: string }) => (
    <section className={'techtyl-billing-panel'} aria-labelledby={'techtyl-billing-ledger-title'}>
        <h2 id={'techtyl-billing-ledger-title'}>{t('ledger_title')}</h2>
        {overview.ledger.length === 0 ? (
            <div className={'techtyl-billing-empty'}>
                <p>{t('ledger_empty')}</p>
                <p className={'techtyl-billing-muted'}>{t('ledger_empty_hint')}</p>
            </div>
        ) : (
            <ul className={'techtyl-billing-ledger'}>
                {overview.ledger.map((entry) => (
                    <li key={entry.id}>
                        <span className={'techtyl-billing-when'}>{dayTime(entry.created_at, locale)}</span>
                        <span className={'techtyl-billing-what'}>
                            {t(REASONS.includes(entry.reason) ? `reason_${entry.reason}` : 'reason_other')}
                            {entry.note !== '' && <em>{entry.note}</em>}
                        </span>
                        <span className={`techtyl-billing-num ${entry.amount < 0 ? 'techtyl-billing-minus' : 'techtyl-billing-plus'}`}>
                            {entry.amount > 0 ? '+' : ''}
                            {money(entry.amount, overview.currency, locale)}
                        </span>
                        <span className={'techtyl-billing-num techtyl-billing-muted'}>{money(entry.balance_after, overview.currency, locale)}</span>
                    </li>
                ))}
            </ul>
        )}
    </section>
);

// ---------------------------------------------------------------------------------------------

export default () => {
    const t = useT(texts);
    const locale = useLocale();
    const [overview, setOverview] = useState<Overview | null>(null);
    const [failed, setFailed] = useState<string | null>(null);
    const [notice, setNotice] = useState<Notice>(null);
    const [returned, setReturned] = useState<string | null>(null);
    const alive = useRef(true);
    const translate = useRef(t);
    translate.current = t;

    const refresh = useCallback(
        () =>
            loadOverview()
                .then((data) => {
                    if (alive.current) {
                        setOverview(data);
                        setFailed(null);
                    }
                })
                .catch((error) => {
                    if (alive.current) {
                        setFailed(errorText(error, translate.current));
                    }
                }),
        []
    );

    useEffect(() => {
        alive.current = true;
        refresh();

        // Back from Stripe: the payment arrives through the webhook, so reload a few times.
        const state = new URLSearchParams(window.location.search).get('zahlung');
        const timers: number[] = [];
        if (state === 'ok') {
            [3000, 7000, 14000, 25000].forEach((delay) => timers.push(window.setTimeout(refresh, delay)));
        }
        if (state) {
            setReturned(state);
            window.history.replaceState(null, '', window.location.pathname);
        }

        return () => {
            alive.current = false;
            timers.forEach((timer) => window.clearTimeout(timer));
        };
    }, [refresh]);

    const showError = (text: string) => {
        setReturned(null);
        setNotice({ tone: 'bad', text });
    };

    // The return notice is derived from the language at render time, so it follows a language change.
    let banner: Notice = notice;
    if (!banner && returned === 'ok') {
        banner = { tone: 'good', text: t('paid_notice') };
    } else if (!banner && returned === 'abbruch') {
        banner = { tone: 'warn', text: t('aborted_notice') };
    }
    const shared = { t, locale, onError: showError };

    return (
        <PageContentBlock title={t('title')}>
            <div className={'techtyl-billing'}>
                <header className={'techtyl-billing-top'}>
                    <div>
                        {/* The theme already prints the page title above the content. */}
                        <h1 className={'techtyl-billing-sr'}>{t('title')}</h1>
                        <p className={'techtyl-billing-muted'}>{t('subtitle')}</p>
                    </div>
                    {overview && overview.test_mode && <span className={'techtyl-billing-chip'}>{t('test_mode')}</span>}
                </header>

                {banner && (
                    <div className={`techtyl-billing-notice techtyl-billing-notice-${banner.tone}`} role={banner.tone === 'bad' ? 'alert' : 'status'}>
                        <span>{banner.text}</span>
                        <button
                            type={'button'}
                            aria-label={t('close_notice')}
                            onClick={() => {
                                setNotice(null);
                                setReturned(null);
                            }}
                        >
                            ×
                        </button>
                    </div>
                )}

                {!overview && !failed && (
                    <div className={'techtyl-billing-cols'} aria-busy={'true'} aria-label={t('loading')}>
                        <div className={'techtyl-billing-panel techtyl-billing-skeleton'} />
                        <div className={'techtyl-billing-panel techtyl-billing-skeleton'} />
                    </div>
                )}

                {!overview && failed && (
                    <div className={'techtyl-billing-panel techtyl-billing-empty'} role={'alert'}>
                        <p>{t('load_failed')}</p>
                        <p className={'techtyl-billing-muted'}>{failed}</p>
                        <button type={'button'} className={'techtyl-billing-btn'} onClick={() => refresh()}>
                            {t('retry')}
                        </button>
                    </div>
                )}

                {overview && (
                    <>
                        <div className={'techtyl-billing-cols'}>
                            <BalancePanel overview={overview} {...shared} />
                            <SubscriptionsPanel overview={overview} {...shared} onChanged={refresh} />
                        </div>
                        <OrderPanel
                            overview={overview}
                            {...shared}
                            onDone={(text) => {
                                setReturned(null);
                                setNotice({ tone: 'good', text });
                                refresh();
                            }}
                        />
                        <LedgerPanel overview={overview} t={t} locale={locale} />
                    </>
                )}
            </div>
        </PageContentBlock>
    );
};
