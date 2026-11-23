import React, { useEffect, useState } from 'react';
import { DesktopComputerIcon, DeviceMobileIcon, FingerPrintIcon, KeyIcon, LogoutIcon, StatusOnlineIcon } from '@heroicons/react/outline';
import updateAccountPassword from '@/api/account/updateAccountPassword';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import AccountLayout from '../Layout';
import TwoFactorCard from '../TwoFactor';
import { ActivityEntry, explain, getActivity, getSecurity, logoutOtherSessions, SessionInfo } from '../api';
import { useAccount } from '../store';
import { deviceLabel, parseUserAgent, passwordStrength, relative } from '../format';
import { Button, Empty, Field, Meter, Modal, Notice, Saved, Section, Skeleton, useSaved } from '../ui';
import { useHashScroll } from './Profile';

export default function AccountSecurity() {
    const t = useT(texts);
    const { account } = useAccount();
    useHashScroll(!!account);

    return (
        <AccountLayout title={t('title_security')} subtitle={t('security_subtitle')}>
            <Section id={'password'} index={0} icon={<KeyIcon />} title={t('password_title')} description={t('password_text')}>
                {account ? <PasswordForm /> : <Skeleton lines={4} />}
            </Section>
            <Section id={'two-factor'} index={1} icon={<FingerPrintIcon />} title={t('totp_title')} description={t('totp_text')}>
                {account ? <TwoFactorCard /> : <Skeleton lines={2} />}
            </Section>
            <Section id={'sessions'} index={2} icon={<StatusOnlineIcon />} title={t('sessions_title')} description={t('sessions_text')}>
                <Sessions />
            </Section>
        </AccountLayout>
    );
}

const PasswordForm = () => {
    const t = useT(texts);
    const { account } = useAccount();
    const [current, setCurrent] = useState('');
    const [next, setNext] = useState('');
    const [confirm, setConfirm] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, flashSaved] = useSaved();
    if (!account) return null;

    const strength = passwordStrength(t, next, [account.username, account.firstName, account.lastName, account.email.split('@')[0]]);
    const mismatch = confirm.length > 0 && confirm !== next;
    const tone = strength.percent < 40 ? 'bad' : strength.percent < 65 ? 'warn' : 'ok';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy) return;
        if (next.length < 8) return setError(t('err_password_short'));
        if (next !== confirm) return setError(t('err_password_confirm'));
        if (next === current) return setError(t('password_same'));
        setBusy(true);
        setError(null);
        updateAccountPassword({ current, password: next, confirmPassword: confirm })
            .then(() => {
                setCurrent('');
                setNext('');
                setConfirm('');
                flashSaved();
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <form onSubmit={submit} className={'techtyl-account-form'} noValidate>
            <Field label={t('current_password')} type={'password'} value={current} onValue={setCurrent} autoComplete={'current-password'} reveal />
            <Field label={t('new_password')} type={'password'} value={next} onValue={setNext} autoComplete={'new-password'} reveal maxLength={255} />
            <div className={'techtyl-account-strength'} aria-live={'polite'}>
                <Meter percent={strength.percent} tone={tone} label={t('strength')} />
                <p>
                    {next && (
                        <b className={`is-${tone}`}>
                            {strength.label} · {strength.percent}&nbsp;%
                        </b>
                    )}
                    <span>{strength.hint}</span>
                </p>
            </div>
            <Field
                label={t('confirm_password')}
                type={'password'}
                value={confirm}
                onValue={setConfirm}
                autoComplete={'new-password'}
                reveal
                error={mismatch ? t('err_password_confirm') : null}
            />
            {error && <Notice tone={'bad'}>{error}</Notice>}
            <div className={'techtyl-account-actions'}>
                <Button variant={'primary'} type={'submit'} busy={busy} disabled={!current || !next || !confirm}>
                    {t('password_save')}
                </Button>
                <Saved show={saved}>{t('password_saved')}</Saved>
            </div>
        </form>
    );
};

const Sessions = () => {
    const t = useT(texts);
    const locale = useLocale();
    const [session, setSession] = useState<SessionInfo | null>(null);
    const [canLogout, setCanLogout] = useState(false);
    const [logins, setLogins] = useState<ActivityEntry[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [dialog, setDialog] = useState(false);
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [dialogError, setDialogError] = useState<string | null>(null);
    const [saved, flashSaved] = useSaved();

    useEffect(() => {
        getSecurity()
            .then((data) => {
                setSession(data.session);
                setCanLogout(data.canLogoutOthers);
            })
            .catch((e) => setError(explain(t, e)));
        getActivity({ event: 'auth:success', perPage: 6 })
            .then((page) => setLogins(page.items))
            .catch(() => setLogins([]));
    }, []);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy || !password) return;
        setBusy(true);
        setDialogError(null);
        logoutOtherSessions(password)
            .then(() => {
                setDialog(false);
                setPassword('');
                flashSaved();
            })
            .catch((e) => setDialogError(explain(t, e)))
            .then(() => setBusy(false));
    };

    const current = parseUserAgent(session?.userAgent);

    return (
        <div className={'techtyl-account-sessions'}>
            {error && <Notice tone={'bad'}>{error}</Notice>}
            {!session && !error ? (
                <Skeleton lines={2} />
            ) : (
                session && (
                    <div className={'techtyl-account-device is-current'}>
                        <span className={'techtyl-account-device-icon'} aria-hidden>
                            {current?.kind === 'mobile' ? <DeviceMobileIcon /> : <DesktopComputerIcon />}
                        </span>
                        <div>
                            <strong>{deviceLabel(t, current)}</strong>
                            <p>
                                <span className={'techtyl-account-live'}>{t('this_device')}</span>
                                {session.ip && <span className={'is-mono'}>{session.ip}</span>}
                            </p>
                        </div>
                    </div>
                )
            )}

            <h3 className={'techtyl-account-subhead'}>{t('recent_logins')}</h3>
            {logins === null ? (
                <Skeleton lines={3} />
            ) : logins.length === 0 ? (
                <Empty title={t('no_logins')}>{t('no_logins_text')}</Empty>
            ) : (
                <ul className={'techtyl-account-devices'}>
                    {logins.map((entry) => {
                        const device = parseUserAgent(typeof entry.properties.useragent === 'string' ? (entry.properties.useragent as string) : null);

                        return (
                            <li key={entry.id} className={'techtyl-account-device'}>
                                <span className={'techtyl-account-device-icon'} aria-hidden>
                                    {device?.kind === 'mobile' ? <DeviceMobileIcon /> : <DesktopComputerIcon />}
                                </span>
                                <div>
                                    <strong>{deviceLabel(t, device)}</strong>
                                    <p>
                                        <time dateTime={entry.timestamp.toISOString()}>{relative(t, locale, entry.timestamp)}</time>
                                        {entry.ip && <span className={'is-mono'}>{entry.ip}</span>}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <div className={'techtyl-account-logout'}>
                <div>
                    <strong>{t('logout_others')}</strong>
                    <p>{t('logout_others_text')}</p>
                </div>
                <div className={'techtyl-account-actions'}>
                    <Saved show={saved}>{t('logout_others_done')}</Saved>
                    <Button icon={<LogoutIcon aria-hidden />} onClick={() => setDialog(true)} disabled={!canLogout}>
                        {t('logout_others_button')}
                    </Button>
                </div>
            </div>
            {!canLogout && session && <p className={'techtyl-account-small'}>{t('logout_others_unavailable')}</p>}

            <Modal
                open={dialog}
                onClose={() => setDialog(false)}
                locked={busy}
                title={t('logout_others')}
                description={t('logout_others_confirm')}
                footer={
                    <>
                        <Button variant={'quiet'} onClick={() => setDialog(false)} disabled={busy}>
                            {t('cancel')}
                        </Button>
                        <Button variant={'primary'} type={'submit'} form={'techtyl-account-logout-form'} busy={busy} disabled={!password}>
                            {t('logout_others_button')}
                        </Button>
                    </>
                }
            >
                <form id={'techtyl-account-logout-form'} onSubmit={submit}>
                    <Field label={t('current_password')} type={'password'} value={password} onValue={setPassword} autoComplete={'current-password'} reveal data-autofocus />
                    {dialogError && <Notice tone={'bad'}>{dialogError}</Notice>}
                </form>
            </Modal>
        </div>
    );
};
