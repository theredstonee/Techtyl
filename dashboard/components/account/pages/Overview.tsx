import React, { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import {
    ArrowRightIcon,
    ChatAlt2Icon,
    CheckIcon,
    ColorSwatchIcon,
    CreditCardIcon,
    KeyIcon,
    LockClosedIcon,
    MailIcon,
    SparklesIcon,
    TerminalIcon,
    UserIcon,
} from '@heroicons/react/outline';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import BeforeContent from '@blueprint/components/Account/Overview/BeforeContent';
import AfterContent from '@blueprint/components/Account/Overview/AfterContent';
import getApiKeys, { ApiKey } from '@/api/account/getApiKeys';
import { useSSHKeys } from '@/api/account/ssh-keys';
import AccountLayout from '../Layout';
import { useAccount } from '../store';
import { ActivityEntry, explain, getActivity } from '../api';
import { Card, Empty, Notice, Ring, Skeleton } from '../ui';
import { EventRow } from '../ActivityList';
import TwoFactorCard from '../TwoFactor';

interface Check {
    key: string;
    done: boolean;
    title: string;
    text: string;
    to: string;
    action: string;
}

export default function AccountOverview() {
    const t = useT(texts);
    const locale = useLocale();
    const { account } = useAccount();
    const { state } = useLocation<undefined | { twoFactorRedirect?: boolean }>();
    const [keys, setKeys] = useState<ApiKey[] | null>(null);
    const [recent, setRecent] = useState<ActivityEntry[] | null>(null);
    const [failed, setFailed] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const { data: sshKeys } = useSSHKeys({ revalidateOnMount: true, revalidateOnFocus: false });

    useEffect(() => {
        getApiKeys()
            .then(setKeys)
            .catch(() => setKeys([]));
        getActivity({ perPage: 6 })
            .then((page) => setRecent(page.items))
            .catch((e) => {
                setRecent([]);
                setError(explain(t, e));
            });
        getActivity({ event: 'auth:fail', perPage: 50 })
            .then((page) => setFailed(page.items.filter((item) => Date.now() - item.timestamp.getTime() < 30 * 86400000).length))
            .catch(() => setFailed(0));
    }, []);

    const checks: Check[] = account
        ? [
              {
                  key: 'totp',
                  done: account.useTotp,
                  title: t('check_totp'),
                  text: account.useTotp ? t('check_totp_ok') : t('check_totp_todo'),
                  to: '/account/security#two-factor',
                  action: t('check_totp_action'),
              },
              {
                  key: 'keys',
                  done: keys !== null && keys.every((key) => key.allowedIps.length > 0),
                  title: t('check_keys'),
                  text:
                      keys === null
                          ? t('checking')
                          : keys.length === 0
                          ? t('check_keys_none')
                          : keys.every((key) => key.allowedIps.length > 0)
                          ? t('check_keys_ok')
                          : t('check_keys_todo', { count: keys.filter((key) => key.allowedIps.length === 0).length }),
                  to: '/account/api',
                  action: t('check_keys_action'),
              },
              {
                  key: 'logins',
                  done: failed === 0,
                  title: t('check_logins'),
                  text: failed === null ? t('checking') : failed === 0 ? t('check_logins_ok') : t('check_logins_todo', { count: failed }),
                  to: '/account/activity?filter=fail',
                  action: t('check_logins_action'),
              },
          ]
        : [];
    const percent = checks.length ? Math.round((checks.filter((check) => check.done).length / checks.length) * 100) : 0;

    const shortcuts = account
        ? [
              { to: '/account/design', icon: <ColorSwatchIcon />, title: t('tab_design'), text: t('short_design'), show: true },
              { to: '/account/tickets', icon: <ChatAlt2Icon />, title: t('tab_tickets'), text: t('short_tickets'), show: account.modules.tickets },
              { to: '/account/billing', icon: <CreditCardIcon />, title: t('tab_billing'), text: t('short_billing'), show: account.modules.billing },
              { to: '/account/assistant', icon: <SparklesIcon />, title: t('tab_assistant'), text: t('short_assistant'), show: account.modules.assistant },
          ].filter((item) => item.show)
        : [];

    return (
        <AccountLayout title={t('title_overview')} hero aside={account && <Ring percent={percent} label={t('protection')} size={92} />}>
            {state?.twoFactorRedirect && (
                <div className={'techtyl-account-rise'}>
                    <Notice tone={'bad'} title={t('totp_required_title')}>
                        {t('totp_required_text')}
                    </Notice>
                    <div className={'techtyl-account-spacer'} />
                    <TwoFactorCard />
                </div>
            )}
            <BeforeContent />
            <div className={'techtyl-account-grid'}>
                <Card className={'techtyl-account-span-2'} index={0}>
                    <div className={'techtyl-account-card-head'}>
                        <div>
                            <h2>{t('protection_title')}</h2>
                            <p>{t('protection_text', { percent })}</p>
                        </div>
                    </div>
                    {!account ? (
                        <Skeleton lines={3} />
                    ) : (
                        <ul className={'techtyl-account-checks'}>
                            {checks.map((check) => (
                                <li key={check.key} className={check.done ? 'is-done' : ''}>
                                    <span className={'techtyl-account-check-mark'} aria-hidden>
                                        {check.done ? <CheckIcon /> : <span />}
                                    </span>
                                    <div>
                                        <strong>{check.title}</strong>
                                        <p>{check.text}</p>
                                    </div>
                                    {!check.done && (
                                        <Link className={'techtyl-account-link'} to={check.to}>
                                            {check.action}
                                            <ArrowRightIcon aria-hidden />
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card index={1}>
                    <div className={'techtyl-account-card-head'}>
                        <div>
                            <h2>{t('signin_title')}</h2>
                            <p>{t('signin_text')}</p>
                        </div>
                    </div>
                    {!account ? (
                        <Skeleton lines={3} />
                    ) : (
                        <dl className={'techtyl-account-facts'}>
                            <div>
                                <dt>
                                    <MailIcon aria-hidden /> {t('email')}
                                </dt>
                                <dd>
                                    <span className={'techtyl-account-truncate'}>{account.email}</span>
                                    <Link to={'/account/profile#email'}>{t('change')}</Link>
                                </dd>
                            </div>
                            <div>
                                <dt>
                                    <UserIcon aria-hidden /> {t('username')}
                                </dt>
                                <dd>
                                    <span className={'is-mono'}>{account.username}</span>
                                </dd>
                            </div>
                            <div>
                                <dt>
                                    <LockClosedIcon aria-hidden /> {t('password')}
                                </dt>
                                <dd>
                                    <span aria-hidden>••••••••••</span>
                                    <Link to={'/account/security#password'}>{t('change')}</Link>
                                </dd>
                            </div>
                        </dl>
                    )}
                </Card>

                <Card className={'techtyl-account-span-2'} index={2}>
                    <div className={'techtyl-account-card-head'}>
                        <div>
                            <h2>{t('recent_title')}</h2>
                            <p>{t('recent_text')}</p>
                        </div>
                        <Link className={'techtyl-account-link'} to={'/account/activity'}>
                            {t('show_all')}
                            <ArrowRightIcon aria-hidden />
                        </Link>
                    </div>
                    {recent === null ? (
                        <Skeleton lines={4} />
                    ) : error ? (
                        <Notice tone={'bad'}>{error}</Notice>
                    ) : recent.length === 0 ? (
                        <Empty title={t('activity_empty')}>{t('activity_empty_text')}</Empty>
                    ) : (
                        <ol className={'techtyl-account-events is-compact'}>
                            {recent.map((entry) => (
                                <EventRow key={entry.id} entry={entry} t={t} locale={locale} compact />
                            ))}
                        </ol>
                    )}
                </Card>

                <Card index={3}>
                    <div className={'techtyl-account-card-head'}>
                        <div>
                            <h2>{t('access_title')}</h2>
                            <p>{t('access_text')}</p>
                        </div>
                    </div>
                    <div className={'techtyl-account-tiles'}>
                        <Link to={'/account/api'} className={'techtyl-account-tile'}>
                            <KeyIcon aria-hidden />
                            <b>{keys === null ? '–' : keys.length}</b>
                            <span>{t('tab_api')}</span>
                        </Link>
                        <Link to={'/account/ssh'} className={'techtyl-account-tile'}>
                            <TerminalIcon aria-hidden />
                            <b>{sshKeys ? sshKeys.length : '–'}</b>
                            <span>{t('tab_ssh')}</span>
                        </Link>
                    </div>
                </Card>

                {shortcuts.length > 0 && (
                    <div className={'techtyl-account-span-3 techtyl-account-shortcuts techtyl-account-rise'} style={{ ['--i' as any]: 4 }}>
                        {shortcuts.map((item) => (
                            <Link key={item.to} to={item.to} className={'techtyl-account-shortcut'}>
                                <span className={'techtyl-account-shortcut-icon'} aria-hidden>
                                    {item.icon}
                                </span>
                                <span>
                                    <strong>{item.title}</strong>
                                    <small>{item.text}</small>
                                </span>
                                <ArrowRightIcon className={'techtyl-account-shortcut-arrow'} aria-hidden />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
            <AfterContent />
        </AccountLayout>
    );
}
