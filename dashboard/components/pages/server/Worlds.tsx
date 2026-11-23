import React, { useEffect, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { backupWorld, deleteWorld, explain, getWorlds, sendPower, ToolsMeta, WorldRow, WorldsView } from '../../tools/api';
import { useToolsT } from '../../tools/text';
import { Button, Dialog, Empty, formatBytes, formatDate, Icon, Notice, SkeletonRows, Toasts, ToolsGate, useToasts } from '../../tools/ui';

const LEVEL_PHRASE = 'DELETE LEVEL';

const roleKey = (role: string): string => {
    if (role === 'overworld' || role === 'nether' || role === 'end' || role === 'other') return 'worlds_role_' + role;

    return 'worlds_role_other';
};

const Page = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const t = useToolsT();
    const { toasts, push, dismiss } = useToasts();
    const canList = !!meta.permissions.read;
    const canArchive = !!meta.permissions.archive;
    const canDelete = !!meta.permissions.remove;
    const canStop = !!meta.permissions.stop;
    const [data, setData] = useState<WorldsView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [busy, setBusy] = useState(false);
    const [backup, setBackup] = useState<WorldRow | null>(null);
    const [acceptRisk, setAcceptRisk] = useState(false);
    const [removing, setRemoving] = useState<WorldRow | null>(null);
    const [typed, setTyped] = useState('');
    const [typedLevel, setTypedLevel] = useState('');

    useEffect(() => {
        if (!canList) return undefined;
        let alive = true;
        setError(null);
        getWorlds(server)
            .then((view) => alive && setData(view))
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, attempt, canList]);

    const offline = data ? data.state === 'offline' : false;

    const runBackup = async () => {
        if (!backup) return;
        const risky = !offline;
        if (risky && !acceptRisk) return;
        setBusy(true);
        try {
            const result = await backupWorld(server, backup.folder, risky);
            push('ok', t('worlds_backup_done', { archive: result.archive }));
            setBackup(null);
            setAcceptRisk(false);
            setAttempt((value) => value + 1);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const runDelete = async () => {
        if (!removing || !offline) return;
        if (typed !== removing.folder) {
            push('danger', t('worlds_mismatch'));

            return;
        }
        if (removing.primary && typedLevel !== LEVEL_PHRASE) {
            push('danger', t('worlds_level_mismatch'));

            return;
        }
        setBusy(true);
        try {
            await deleteWorld(server, removing.folder, typed, removing.primary ? LEVEL_PHRASE : '');
            push('ok', t('worlds_deleted', { folder: removing.folder }));
            setRemoving(null);
            setTyped('');
            setTypedLevel('');
            setAttempt((value) => value + 1);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const stop = async () => {
        setBusy(true);
        try {
            await sendPower(server, 'stop');
            push('ok', t('worlds_stopped'));
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    if (!canList) {
        return (
            <Empty icon={'warn'} title={t('need_list_title')}>
                {t('need_list_text')}
            </Empty>
        );
    }

    return (
        <>
            <Toasts toasts={toasts} dismiss={dismiss} />
            <div className={'techtyl-tools-top'}>
                <p>{t('worlds_intro')}</p>
                <Button small look={'ghost'} onClick={() => setAttempt((value) => value + 1)} disabled={busy}>
                    <Icon id={'refresh'} size={16} />
                    {t('refresh')}
                </Button>
            </div>
            <p className={'techtyl-tools-muted'}>{t('worlds_inside')}</p>
            <p className={'techtyl-tools-muted'}>{t('worlds_size_hint')}</p>
            {error && (
                <Empty icon={'warn'} title={t('load_failed')} action={<Button onClick={() => setAttempt((value) => value + 1)}>{t('retry')}</Button>}>
                    {error}
                </Empty>
            )}
            {!error && !data && (
                <div className={'techtyl-tools-panel'} style={{ marginTop: 12 }}>
                    <SkeletonRows rows={4} />
                </div>
            )}
            {data && data.level_name == null && (
                <div style={{ marginTop: 12 }}>
                    <Notice tone={'warning'}>{t('worlds_level_unknown')}</Notice>
                </div>
            )}
            {data && data.truncated && (
                <div style={{ marginTop: 12 }}>
                    <Notice tone={'info'}>{t('worlds_truncated')}</Notice>
                </div>
            )}
            {data && data.worlds.length === 0 && (
                <Empty icon={'box'} title={t('worlds_empty_title')}>
                    {t('worlds_empty_text')}
                </Empty>
            )}
            {data && data.worlds.length > 0 && (
                <div className={'techtyl-tools-panel'} style={{ marginTop: 12 }}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>{t('players_state')}: {t('state_' + (data.state === 'offline' || data.state === 'running' || data.state === 'starting' || data.state === 'stopping' ? data.state : 'unknown'))}</h2>
                    </div>
                    {data.worlds.map((world) => (
                        <div key={world.folder} className={'techtyl-tools-world'}>
                            <div className={'techtyl-tools-world-main'}>
                                <strong>
                                    {world.folder}
                                    <span className={'techtyl-tools-pill'}>{t(roleKey(world.role))}</span>
                                    {world.primary && <span className={'techtyl-tools-pill'}>{t('worlds_primary')}</span>}
                                </strong>
                                <span className={'techtyl-tools-muted'}>
                                    {world.size != null ? formatBytes(world.size) : ''}
                                    {world.size != null && world.modified ? ' · ' : ''}
                                    {world.modified ? formatDate(world.modified) : ''}
                                </span>
                            </div>
                            <div className={'techtyl-tools-actions'}>
                                <Button
                                    small
                                    look={'plain'}
                                    disabled={!canArchive || busy}
                                    onClick={() => {
                                        setAcceptRisk(false);
                                        setBackup(world);
                                    }}
                                >
                                    {t('worlds_backup')}
                                </Button>
                                <Button
                                    small
                                    look={'danger'}
                                    disabled={!canDelete || busy}
                                    onClick={() => {
                                        setTyped('');
                                        setTypedLevel('');
                                        setRemoving(world);
                                    }}
                                >
                                    {t('worlds_delete')}
                                </Button>
                            </div>
                        </div>
                    ))}
                    {!canArchive && <p className={'techtyl-tools-muted'}>{t('worlds_no_archive')}</p>}
                    {!canDelete && <p className={'techtyl-tools-muted'}>{t('worlds_no_delete')}</p>}
                </div>
            )}

            {backup && (
                <Dialog
                    title={t('worlds_backup_title', { folder: backup.folder })}
                    onClose={() => !busy && setBackup(null)}
                    footer={
                        <>
                            <Button look={'ghost'} disabled={busy} onClick={() => setBackup(null)}>
                                {t('cancel')}
                            </Button>
                            <Button look={'primary'} busy={busy} disabled={!offline && !acceptRisk} onClick={runBackup}>
                                {t('worlds_backup_go')}
                            </Button>
                        </>
                    }
                >
                    <p>{t('worlds_backup_text')}</p>
                    {!offline && (
                        <>
                            <div style={{ marginTop: 10 }}>
                                <Notice tone={'warning'}>{t('worlds_backup_running')}</Notice>
                            </div>
                            <label className={'techtyl-tools-checkline'}>
                                <input type={'checkbox'} checked={acceptRisk} onChange={(event) => setAcceptRisk(event.target.checked)} />
                                <span>{t('worlds_backup_accept')}</span>
                            </label>
                        </>
                    )}
                </Dialog>
            )}

            {removing && (
                <Dialog
                    title={t('worlds_delete_title', { folder: removing.folder })}
                    onClose={() => !busy && setRemoving(null)}
                    footer={
                        <>
                            <Button look={'ghost'} disabled={busy} onClick={() => setRemoving(null)}>
                                {t('cancel')}
                            </Button>
                            {offline && (
                                <Button look={'danger'} busy={busy} disabled={typed !== removing.folder || (removing.primary && typedLevel !== LEVEL_PHRASE)} onClick={runDelete}>
                                    {t('worlds_delete_go')}
                                </Button>
                            )}
                        </>
                    }
                >
                    <p>{t('worlds_delete_text')}</p>
                    {!offline && (
                        <>
                            <div style={{ marginTop: 10 }}>
                                <Notice tone={'warning'}>{t('worlds_delete_offline')}</Notice>
                            </div>
                            {canStop && (
                                <Button look={'plain'} busy={busy} onClick={stop}>
                                    {t('worlds_stop')}
                                </Button>
                            )}
                        </>
                    )}
                    {offline && (
                        <>
                            {data && data.level_name == null && <p className={'techtyl-tools-muted'}>{t('worlds_level_unknown')}</p>}
                            <div className={'techtyl-tools-field'} style={{ marginTop: 10 }}>
                                <label htmlFor={'techtyl-world-confirm'}>{t('worlds_type_folder')}</label>
                                <input id={'techtyl-world-confirm'} value={typed} maxLength={64} autoComplete={'off'} spellCheck={false} onChange={(event) => setTyped(event.target.value)} />
                            </div>
                            {removing.primary && (
                                <div className={'techtyl-tools-field'} style={{ marginTop: 10 }}>
                                    <label htmlFor={'techtyl-world-level'}>{t('worlds_type_level')}</label>
                                    <input id={'techtyl-world-level'} value={typedLevel} maxLength={32} autoComplete={'off'} spellCheck={false} onChange={(event) => setTypedLevel(event.target.value)} />
                                </div>
                            )}
                        </>
                    )}
                </Dialog>
            )}
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_worlds')}>
            <ToolsGate tool={'worlds'}>{(meta, server) => <Page meta={meta} server={server} />}</ToolsGate>
        </ServerContentBlock>
    );
};
