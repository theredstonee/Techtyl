import React, { useEffect, useState } from 'react';
import { PlusIcon, TerminalIcon, TrashIcon } from '@heroicons/react/outline';
import { createSSHKey, deleteSSHKey, useSSHKeys } from '@/api/account/ssh-keys';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import AccountLayout from '../Layout';
import { explain } from '../api';
import { shortDate, shortFingerprint } from '../format';
import { Area, Button, Card, Empty, Field, Modal, Notice, Skeleton } from '../ui';

const KEY_PATTERN = /^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-(ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com) [A-Za-z0-9+/]{40,}={0,3}( .*)?$/;

export default function AccountSshKeys() {
    const t = useT(texts);
    const locale = useLocale();
    const { data: keys, error: loadError, mutate, revalidate } = useSSHKeys({ revalidateOnMount: true, revalidateOnFocus: false });
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState<{ name: string; fingerprint: string } | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const remove = () => {
        if (!removing) return;
        setBusy(true);
        setError(null);
        deleteSSHKey(removing.fingerprint)
            .then(() => {
                mutate((list) => (list || []).filter((key) => key.fingerprint !== removing.fingerprint), false);
                setRemoving(null);
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <AccountLayout title={t('title_ssh')} subtitle={t('ssh_subtitle')}>
            <Card index={0}>
                <div className={'techtyl-account-card-head'}>
                    <div>
                        <h2>{t('ssh_title')}</h2>
                        <p>{t('ssh_text')}</p>
                    </div>
                    <Button variant={'primary'} icon={<PlusIcon aria-hidden />} onClick={() => setAdding(true)}>
                        {t('ssh_add')}
                    </Button>
                </div>
                {loadError && (
                    <Notice tone={'bad'} title={t('load_failed')} action={<Button small onClick={() => revalidate()}>{t('retry')}</Button>}>
                        {explain(t, loadError)}
                    </Notice>
                )}
                {!keys ? (
                    !loadError && <Skeleton lines={3} />
                ) : keys.length === 0 ? (
                    <Empty
                        icon={<TerminalIcon />}
                        title={t('ssh_empty')}
                        action={
                            <Button icon={<PlusIcon aria-hidden />} onClick={() => setAdding(true)}>
                                {t('ssh_add')}
                            </Button>
                        }
                    >
                        {t('ssh_empty_text')}
                    </Empty>
                ) : (
                    <ul className={'techtyl-account-keys'}>
                        {keys.map((key, index) => (
                            <li key={key.fingerprint} className={'techtyl-account-key techtyl-account-rise'} style={{ ['--i' as any]: Math.min(index, 8) }}>
                                <span className={'techtyl-account-key-icon'} aria-hidden>
                                    <TerminalIcon />
                                </span>
                                <div className={'techtyl-account-key-body'}>
                                    <strong>{key.name}</strong>
                                    <p>
                                        <code title={key.fingerprint}>{shortFingerprint(key.fingerprint)}</code>
                                        <span className={'techtyl-account-dot'} aria-hidden />
                                        <span>{t('created_on', { date: shortDate(key.createdAt, locale) })}</span>
                                    </p>
                                </div>
                                <Button variant={'quiet'} small icon={<TrashIcon aria-hidden />} onClick={() => setRemoving(key)} aria-label={t('ssh_delete_label', { name: key.name })}>
                                    {t('delete')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <AddDialog
                open={adding}
                onClose={() => setAdding(false)}
                onAdded={(key) => {
                    mutate((list) => [...(list || []), key], false);
                    setAdding(false);
                }}
            />

            <Modal
                open={!!removing}
                onClose={() => setRemoving(null)}
                locked={busy}
                tone={'danger'}
                title={t('ssh_delete_title')}
                description={removing ? t('ssh_delete_text', { name: removing.name }) : ''}
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
                {removing && <code className={'techtyl-account-block-code'}>{removing.fingerprint}</code>}
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </Modal>
        </AccountLayout>
    );
}

const AddDialog = ({ open, onClose, onAdded }: { open: boolean; onClose: () => void; onAdded: (key: any) => void }) => {
    const t = useT(texts);
    const [keyName, setKeyName] = useState('');
    const [publicKey, setPublicKey] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setKeyName('');
            setPublicKey('');
            setError(null);
        }
    }, [open]);

    const trimmed = publicKey.trim().replace(/\s+/g, ' ');
    const looksValid = trimmed === '' || KEY_PATTERN.test(trimmed);

    const onKeyValue = (value: string) => {
        setPublicKey(value);
        // Suggest the comment of the key (usually user@host) as the name.
        const comment = value.trim().split(/\s+/)[2];
        if (!keyName && comment && comment.length <= 60) setKeyName(comment);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy) return;
        if (!keyName.trim()) return setError(t('ssh_name_required'));
        if (!trimmed || !looksValid) return setError(t('err_ssh_invalid'));
        setBusy(true);
        setError(null);
        createSSHKey(keyName.trim(), trimmed)
            .then(onAdded)
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            locked={busy}
            wide
            title={t('ssh_add_title')}
            description={t('ssh_add_text')}
            footer={
                <>
                    <Button variant={'quiet'} onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button variant={'primary'} type={'submit'} form={'techtyl-account-ssh-form'} busy={busy} disabled={!keyName.trim() || !trimmed}>
                        {t('ssh_add')}
                    </Button>
                </>
            }
        >
            <form id={'techtyl-account-ssh-form'} onSubmit={submit} className={'techtyl-account-form'}>
                <Area
                    label={t('ssh_public_key')}
                    value={publicKey}
                    onValue={onKeyValue}
                    rows={5}
                    mono
                    spellCheck={false}
                    placeholder={'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA… user@host'}
                    hint={t('ssh_public_key_hint')}
                    error={!looksValid ? t('err_ssh_invalid') : null}
                    data-autofocus
                />
                <Field label={t('ssh_name')} value={keyName} onValue={setKeyName} maxLength={191} hint={t('ssh_name_hint')} />
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </form>
        </Modal>
    );
};
