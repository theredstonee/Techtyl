import React, { useEffect, useState } from 'react';
import { KeyIcon, PlusIcon, TrashIcon } from '@heroicons/react/outline';
import getApiKeys, { ApiKey } from '@/api/account/getApiKeys';
import createApiKey from '@/api/account/createApiKey';
import deleteApiKey from '@/api/account/deleteApiKey';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import AccountLayout from '../Layout';
import { explain } from '../api';
import { relative, shortDate } from '../format';
import { Area, Button, Card, CopyValue, Empty, Field, Modal, Notice, Skeleton } from '../ui';

const IP_PATTERN = /^(\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?|[0-9a-f:]+(\/\d{1,3})?)$/i;

export default function AccountApiKeys() {
    const t = useT(texts);
    const locale = useLocale();
    const [keys, setKeys] = useState<ApiKey[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    const [secret, setSecret] = useState('');
    const [removing, setRemoving] = useState<ApiKey | null>(null);
    const [busy, setBusy] = useState(false);
    const [removeError, setRemoveError] = useState<string | null>(null);

    const load = () => {
        setError(null);
        getApiKeys()
            .then(setKeys)
            .catch((e) => setError(explain(t, e)));
    };
    useEffect(load, []);

    const remove = () => {
        if (!removing) return;
        setBusy(true);
        setRemoveError(null);
        deleteApiKey(removing.identifier)
            .then(() => {
                setKeys((list) => (list || []).filter((key) => key.identifier !== removing.identifier));
                setRemoving(null);
            })
            .catch((e) => setRemoveError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <AccountLayout title={t('title_api')} subtitle={t('api_subtitle')}>
            <Card index={0}>
                <div className={'techtyl-account-card-head'}>
                    <div>
                        <h2>{t('api_title')}</h2>
                        <p>{t('api_text')}</p>
                    </div>
                    <Button variant={'primary'} icon={<PlusIcon aria-hidden />} onClick={() => setCreating(true)}>
                        {t('api_create')}
                    </Button>
                </div>
                {error && (
                    <Notice tone={'bad'} title={t('load_failed')} action={<Button small onClick={load}>{t('retry')}</Button>}>
                        {error}
                    </Notice>
                )}
                {keys === null ? (
                    !error && <Skeleton lines={3} />
                ) : keys.length === 0 ? (
                    <Empty
                        icon={<KeyIcon />}
                        title={t('api_empty')}
                        action={
                            <Button icon={<PlusIcon aria-hidden />} onClick={() => setCreating(true)}>
                                {t('api_create')}
                            </Button>
                        }
                    >
                        {t('api_empty_text')}
                    </Empty>
                ) : (
                    <ul className={'techtyl-account-keys'}>
                        {keys.map((key, index) => (
                            <li key={key.identifier} className={'techtyl-account-key techtyl-account-rise'} style={{ ['--i' as any]: Math.min(index, 8) }}>
                                <span className={'techtyl-account-key-icon'} aria-hidden>
                                    <KeyIcon />
                                </span>
                                <div className={'techtyl-account-key-body'}>
                                    <strong>{key.description}</strong>
                                    <p>
                                        <code>{key.identifier}</code>
                                        <span className={'techtyl-account-dot'} aria-hidden />
                                        <span>{key.createdAt ? t('created_on', { date: shortDate(key.createdAt, locale) }) : ''}</span>
                                        <span className={'techtyl-account-dot'} aria-hidden />
                                        <span>{key.lastUsedAt ? t('last_used', { time: relative(t, locale, key.lastUsedAt) }) : t('never_used')}</span>
                                    </p>
                                    <div className={'techtyl-account-ips'}>
                                        {key.allowedIps.length === 0 ? (
                                            <span className={'techtyl-account-badge is-warn'}>{t('api_any_ip')}</span>
                                        ) : (
                                            key.allowedIps.map((ip) => (
                                                <span key={ip} className={'techtyl-account-badge'}>
                                                    {ip}
                                                </span>
                                            ))
                                        )}
                                    </div>
                                </div>
                                <Button variant={'quiet'} small icon={<TrashIcon aria-hidden />} onClick={() => setRemoving(key)} aria-label={t('api_delete_label', { name: key.description })}>
                                    {t('delete')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <CreateDialog
                open={creating}
                onClose={() => setCreating(false)}
                onCreated={(key, token) => {
                    setKeys((list) => [...(list || []), key]);
                    setCreating(false);
                    setSecret(token);
                }}
            />

            <Modal
                open={!!secret}
                onClose={() => setSecret('')}
                title={t('api_secret_title')}
                description={t('api_secret_text')}
                footer={
                    <Button variant={'primary'} onClick={() => setSecret('')}>
                        {t('done')}
                    </Button>
                }
            >
                <CopyValue value={secret} label={t('api_secret_label')} secret />
                <Notice tone={'warn'}>{t('api_secret_warning')}</Notice>
            </Modal>

            <Modal
                open={!!removing}
                onClose={() => setRemoving(null)}
                locked={busy}
                tone={'danger'}
                title={t('api_delete_title')}
                description={removing ? t('api_delete_text', { name: removing.description }) : ''}
                footer={
                    <>
                        <Button variant={'quiet'} onClick={() => setRemoving(null)} disabled={busy}>
                            {t('cancel')}
                        </Button>
                        <Button variant={'danger'} onClick={remove} busy={busy} data-autofocus>
                            {t('delete')}
                        </Button>
                    </>
                }
            >
                {removing && <code className={'techtyl-account-block-code'}>{removing.identifier}</code>}
                {removeError && <Notice tone={'bad'}>{removeError}</Notice>}
            </Modal>
        </AccountLayout>
    );
}

const CreateDialog = ({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (key: ApiKey, token: string) => void }) => {
    const t = useT(texts);
    const [description, setDescription] = useState('');
    const [ips, setIps] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setDescription('');
            setIps('');
            setError(null);
        }
    }, [open]);

    const list = ips
        .split(/[\s,]+/)
        .map((ip) => ip.trim())
        .filter(Boolean);
    const invalid = list.filter((ip) => !IP_PATTERN.test(ip));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy) return;
        if (!description.trim()) return setError(t('err_description'));
        if (invalid.length > 0) return setError(t('api_ips_invalid', { list: invalid.join(', ') }));
        setBusy(true);
        setError(null);
        createApiKey(description.trim(), list.join('\n'))
            .then(({ secretToken, ...key }) => onCreated(key, `${key.identifier}${secretToken}`))
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            locked={busy}
            title={t('api_create_title')}
            description={t('api_create_text')}
            footer={
                <>
                    <Button variant={'quiet'} onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button variant={'primary'} type={'submit'} form={'techtyl-account-api-form'} busy={busy} disabled={!description.trim()}>
                        {t('api_create')}
                    </Button>
                </>
            }
        >
            <form id={'techtyl-account-api-form'} onSubmit={submit} className={'techtyl-account-form'}>
                <Field label={t('api_description')} value={description} onValue={setDescription} maxLength={500} hint={t('api_description_hint')} data-autofocus />
                <Area
                    label={t('api_ips')}
                    value={ips}
                    onValue={setIps}
                    rows={4}
                    mono
                    placeholder={'203.0.113.7\n198.51.100.0/24'}
                    hint={t('api_ips_hint')}
                    error={invalid.length > 0 ? t('api_ips_invalid', { list: invalid.join(', ') }) : null}
                />
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </form>
        </Modal>
    );
};
