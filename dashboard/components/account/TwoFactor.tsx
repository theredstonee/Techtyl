import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode.react';
import { DeviceMobileIcon, DownloadIcon, ShieldCheckIcon, ShieldExclamationIcon } from '@heroicons/react/outline';
import { useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import getTwoFactorTokenData, { TwoFactorTokenData } from '@/api/account/getTwoFactorTokenData';
import enableAccountTwoFactor from '@/api/account/enableAccountTwoFactor';
import disableAccountTwoFactor from '@/api/account/disableAccountTwoFactor';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import { explain } from './api';
import { patchAccount, useAccount } from './store';
import { Button, CopyValue, Field, Modal, Notice, fallbackCopy } from './ui';

/** Status and setup of two-factor sign-in (panel API: /api/client/account/two-factor). */
export default function TwoFactorCard() {
    const t = useT(texts);
    const { account } = useAccount();
    const updateUserData = useStoreActions((actions: any) => (actions as ApplicationStore & any).user.updateUserData);
    const [dialog, setDialog] = useState<'enable' | 'disable' | null>(null);
    const [tokens, setTokens] = useState<string[]>([]);

    const enabled = !!account?.useTotp;
    const done = (on: boolean) => {
        patchAccount({ useTotp: on });
        updateUserData({ useTotp: on });
    };

    return (
        <div className={'techtyl-account-totp'}>
            <div className={`techtyl-account-status ${enabled ? 'is-ok' : 'is-warn'}`}>
                <span className={'techtyl-account-status-icon'} aria-hidden>
                    {enabled ? <ShieldCheckIcon /> : <ShieldExclamationIcon />}
                </span>
                <div>
                    <strong>{enabled ? t('totp_on') : t('totp_off')}</strong>
                    <p>{enabled ? t('totp_on_text') : t('totp_off_text')}</p>
                </div>
            </div>
            <div className={'techtyl-account-actions'}>
                {enabled ? (
                    <Button variant={'danger'} onClick={() => setDialog('disable')}>
                        {t('totp_disable')}
                    </Button>
                ) : (
                    <Button variant={'primary'} icon={<DeviceMobileIcon aria-hidden />} onClick={() => setDialog('enable')}>
                        {t('totp_enable')}
                    </Button>
                )}
            </div>

            <EnableDialog
                open={dialog === 'enable'}
                onClose={() => setDialog(null)}
                onEnabled={(list) => {
                    done(true);
                    setDialog(null);
                    setTokens(list);
                }}
            />
            <DisableDialog
                open={dialog === 'disable'}
                onClose={() => setDialog(null)}
                onDisabled={() => {
                    done(false);
                    setDialog(null);
                }}
            />
            <RecoveryDialog tokens={tokens} onClose={() => setTokens([])} />
        </div>
    );
}

const EnableDialog = ({ open, onClose, onEnabled }: { open: boolean; onClose: () => void; onEnabled: (tokens: string[]) => void }) => {
    const t = useT(texts);
    const [token, setToken] = useState<TwoFactorTokenData | null>(null);
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;
        setToken(null);
        setCode('');
        setPassword('');
        setError(null);
        getTwoFactorTokenData()
            .then(setToken)
            .catch((e) => setError(explain(t, e)));
    }, [open]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy || code.length !== 6 || !password) return;
        setBusy(true);
        setError(null);
        enableAccountTwoFactor(code, password)
            .then(onEnabled)
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            locked={busy}
            wide
            title={t('totp_setup_title')}
            description={t('totp_setup_text')}
            footer={
                <>
                    <Button variant={'quiet'} onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button variant={'primary'} type={'submit'} form={'techtyl-account-totp-form'} busy={busy} disabled={!token || code.length !== 6 || !password}>
                        {t('totp_enable')}
                    </Button>
                </>
            }
        >
            <form id={'techtyl-account-totp-form'} onSubmit={submit} className={'techtyl-account-totp-setup'}>
                <ol className={'techtyl-account-steps'}>
                    <li>
                        <span className={'techtyl-account-step-no'}>1</span>
                        <div>
                            <strong>{t('totp_step_scan')}</strong>
                            <p>{t('totp_step_scan_text')}</p>
                            <div className={'techtyl-account-qr'}>
                                {token ? (
                                    <QRCode renderAs={'svg'} value={token.image_url_data} size={168} includeMargin />
                                ) : (
                                    <span className={'techtyl-account-spinner is-large'} aria-label={t('loading')} />
                                )}
                            </div>
                            {token && (
                                <>
                                    <p className={'techtyl-account-small'}>{t('totp_manual')}</p>
                                    <CopyValue value={(token.secret.match(/.{1,4}/g) || []).join(' ')} label={t('totp_secret')} />
                                </>
                            )}
                        </div>
                    </li>
                    <li>
                        <span className={'techtyl-account-step-no'}>2</span>
                        <div>
                            <strong>{t('totp_step_confirm')}</strong>
                            <Field
                                label={t('totp_code')}
                                value={code}
                                onValue={(value) => setCode(value.replace(/\D/g, '').slice(0, 6))}
                                inputMode={'numeric'}
                                autoComplete={'one-time-code'}
                                placeholder={'000000'}
                                mono
                                className={'techtyl-account-code-field'}
                                data-autofocus
                            />
                            <Field label={t('current_password')} type={'password'} value={password} onValue={setPassword} autoComplete={'current-password'} reveal />
                        </div>
                    </li>
                </ol>
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </form>
        </Modal>
    );
};

const DisableDialog = ({ open, onClose, onDisabled }: { open: boolean; onClose: () => void; onDisabled: () => void }) => {
    const t = useT(texts);
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setPassword('');
            setError(null);
        }
    }, [open]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy || !password) return;
        setBusy(true);
        setError(null);
        disableAccountTwoFactor(password)
            .then(onDisabled)
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            locked={busy}
            tone={'danger'}
            title={t('totp_disable_title')}
            description={t('totp_disable_text')}
            footer={
                <>
                    <Button variant={'quiet'} onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button variant={'danger'} type={'submit'} form={'techtyl-account-totp-off'} busy={busy} disabled={!password}>
                        {t('totp_disable')}
                    </Button>
                </>
            }
        >
            <form id={'techtyl-account-totp-off'} onSubmit={submit}>
                <Field label={t('current_password')} type={'password'} value={password} onValue={setPassword} autoComplete={'current-password'} reveal data-autofocus />
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </form>
        </Modal>
    );
};

const RecoveryDialog = ({ tokens, onClose }: { tokens: string[]; onClose: () => void }) => {
    const t = useT(texts);
    const [saved, setSaved] = useState(false);
    const text = tokens.join('\n');

    useEffect(() => setSaved(false), [tokens.length]);

    const download = () => {
        const blob = new Blob([`${t('recovery_file_head')}\n\n${text}\n`], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'recovery-codes.txt';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        setSaved(true);
    };

    const copyAll = () => {
        const done = () => setSaved(true);
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, () => fallbackCopy(text) && done());
        else if (fallbackCopy(text)) done();
    };

    return (
        <Modal
            open={tokens.length > 0}
            onClose={() => saved && onClose()}
            locked={!saved}
            title={t('recovery_title')}
            description={t('recovery_text')}
            footer={
                <Button variant={'primary'} onClick={onClose} disabled={!saved} title={saved ? undefined : t('recovery_save_first')}>
                    {t('recovery_done')}
                </Button>
            }
        >
            <ul className={'techtyl-account-recovery'}>
                {tokens.map((token) => (
                    <li key={token}>
                        <code>{token}</code>
                    </li>
                ))}
            </ul>
            <div className={'techtyl-account-actions'}>
                <Button icon={<DownloadIcon aria-hidden />} onClick={download} data-autofocus>
                    {t('recovery_download')}
                </Button>
                <Button onClick={copyAll}>{t('recovery_copy')}</Button>
            </div>
            <Notice tone={saved ? 'ok' : 'warn'}>{saved ? t('recovery_saved') : t('recovery_warning')}</Notice>
        </Modal>
    );
};
