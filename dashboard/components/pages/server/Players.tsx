import React, { useEffect, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { explain, getPlayers, PlayerAction, playerCommand, playerFile, PlayerRow, PlayersView, ToolsMeta } from '../../tools/api';
import { useToolsT } from '../../tools/text';
import { Button, Empty, Icon, Notice, Segmented, SkeletonRows, Toasts, ToolsGate, useToasts } from '../../tools/ui';

const NAME = /^\.?[A-Za-z0-9_]{1,16}$/;
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

type Tab = 'online' | 'whitelist' | 'ops' | 'bans' | 'ips';

const nameOk = (value: string): boolean => {
    const label = value.trim();

    return label.length > 0 && label.length <= 17 && NAME.test(label);
};

const reasonOk = (value: string): boolean => {
    const text = value.trim();
    if (text.length > 100) return false;
    for (let index = 0; index < text.length; index++) {
        const code = text.charCodeAt(index);
        if (code < 32 || code === 127) return false;
    }

    return text.indexOf('\u2028') === -1 && text.indexOf('\u2029') === -1;
};

const Head = ({ uuid, label }: { uuid: string | null; label: string }) => {
    const [broken, setBroken] = useState(false);
    const safe = !!uuid && UUID.test(uuid);
    const letters = (label.replace(/[^A-Za-z0-9]/g, '').slice(0, 2) || '?').toUpperCase();
    if (!safe || broken) {
        return (
            <span className={'techtyl-tools-head'} aria-hidden={'true'}>
                {letters}
            </span>
        );
    }
    const src = 'https://mc-heads.net/avatar/' + uuid.toLowerCase() + '/32';

    return (
        <span className={'techtyl-tools-head'}>
            <img src={src} alt={''} width={32} height={32} loading={'lazy'} referrerPolicy={'no-referrer'} onError={() => setBroken(true)} />
        </span>
    );
};

const Page = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const t = useToolsT();
    const { toasts, push, dismiss } = useToasts();
    const canRead = !!meta.permissions.read_content;
    const canConsole = !!meta.permissions.console;
    const canWrite = !!meta.permissions.update_files;
    const [data, setData] = useState<PlayersView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [tab, setTab] = useState<Tab>('online');
    const [label, setLabel] = useState('');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (!canRead) return undefined;
        let alive = true;
        setError(null);
        getPlayers(server)
            .then((view) => alive && setData(view))
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, attempt, canRead]);

    const running = data ? data.state === 'running' : false;
    const offline = data ? data.state === 'offline' : false;
    const useConsole = running && canConsole;
    const useFile = offline && canWrite;

    const send = async (action: PlayerAction, playerLabel: string, because: string) => {
        const trimmed = playerLabel.trim();
        const why = because.trim();
        if (!nameOk(trimmed)) {
            push('danger', t('players_bad_name'));

            return;
        }
        if (!reasonOk(why)) {
            push('danger', t('players_bad_reason'));

            return;
        }
        if (action === 'kick') {
            if (!running || !canConsole) {
                const waiting = data && (data.state === 'starting' || data.state === 'stopping');
                push('danger', waiting ? t('players_wait') : running ? t('players_no_console') : t('players_kick_off'));

                return;
            }
        } else if (useConsole) {
            // Console while the server is running.
        } else if (!useFile) {
            push('danger', data && (data.state === 'starting' || data.state === 'stopping') ? t('players_wait') : running ? t('players_no_console') : t('players_no_files'));

            return;
        }
        setBusy(true);
        try {
            if (action === 'kick' || useConsole) {
                await playerCommand(server, action, trimmed, why);
            } else {
                await playerFile(server, action, trimmed, why);
            }
            setLabel('');
            setReason('');
            push('ok', t('players_done'));
            setAttempt((value) => value + 1);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const rowActions = (row: PlayerRow, remove: PlayerAction) => {
        const playerLabel = row.name;
        if (!row.actionable) {
            return <span className={'techtyl-tools-muted'}>{t('players_not_actionable')}</span>;
        }

        return (
            <div className={'techtyl-tools-actions'}>
                <Button small look={'danger'} busy={busy} onClick={() => send(remove, playerLabel, '')}>
                    {t(remove === 'deop' ? 'players_deop' : remove === 'pardon' ? 'players_pardon' : 'players_remove')}
                </Button>
            </div>
        );
    };

    if (!canRead) {
        return (
            <Empty icon={'warn'} title={t('need_content_title')}>
                {t('need_content_text')}
            </Empty>
        );
    }

    const list = data ? (tab === 'whitelist' ? data.whitelist : tab === 'ops' ? data.ops : tab === 'bans' ? data.bans : null) : null;

    return (
        <>
            <Toasts toasts={toasts} dismiss={dismiss} />
            <div className={'techtyl-tools-top'}>
                <p>{t('players_intro')}</p>
                <Button small look={'ghost'} onClick={() => setAttempt((value) => value + 1)} disabled={busy}>
                    <Icon id={'refresh'} size={16} />
                    {t('refresh')}
                </Button>
            </div>
            {error && (
                <Empty icon={'warn'} title={t('load_failed')} action={<Button onClick={() => setAttempt((value) => value + 1)}>{t('retry')}</Button>}>
                    {error}
                </Empty>
            )}
            {!error && !data && (
                <div className={'techtyl-tools-panel'}>
                    <SkeletonRows rows={5} />
                </div>
            )}
            {data && (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>
                            {t('players_state')}: {t('state_' + (data.state === 'offline' || data.state === 'running' || data.state === 'starting' || data.state === 'stopping' ? data.state : 'unknown'))}
                        </h2>
                    </div>
                    {running && canConsole && <p className={'techtyl-tools-muted'}>{t('players_via_console')}</p>}
                    {offline && <p className={'techtyl-tools-muted'}>{t('players_via_file')}</p>}
                    {(data.state === 'starting' || data.state === 'stopping' || data.state === 'unknown') && <Notice tone={'info'}>{t('players_wait')}</Notice>}
                    {!data.online_mode && <Notice tone={'info'}>{t('players_online_off')}</Notice>}
                    <div style={{ margin: '12px 0' }}>
                        <Segmented
                            label={t('page_players')}
                            value={tab}
                            onChange={(value) => setTab(value)}
                            options={[
                                { id: 'online' as Tab, label: t('players_tab_online') },
                                { id: 'whitelist' as Tab, label: t('players_tab_whitelist') },
                                { id: 'ops' as Tab, label: t('players_tab_ops') },
                                { id: 'bans' as Tab, label: t('players_tab_bans') },
                                { id: 'ips' as Tab, label: t('players_tab_ips') },
                            ]}
                        />
                    </div>

                    {tab === 'online' && (
                        <div>
                            {data.query.status !== 'ok' ? (
                                <Notice tone={'info'} title={t('players_unknown_title')}>
                                    {t('players_unknown_text')}
                                </Notice>
                            ) : (
                                <>
                                    <p>
                                        {t('players_online_count', {
                                            online: data.query.online == null ? 0 : data.query.online,
                                            max: data.query.max == null ? 0 : data.query.max,
                                        })}
                                    </p>
                                    {data.query.sample.length === 0 ? (
                                        <p className={'techtyl-tools-muted'}>{t('players_none_online')}</p>
                                    ) : (
                                        <>
                                            <p className={'techtyl-tools-muted'}>{t('players_sample')}</p>
                                            {data.query.sample.map((row) => {
                                                const playerLabel = row.name;

                                                return (
                                                    <div key={playerLabel + (row.uuid || '')} className={'techtyl-tools-player'}>
                                                        <Head uuid={row.uuid} label={playerLabel} />
                                                        <div className={'techtyl-tools-player-main'}>
                                                            <strong>{playerLabel}</strong>
                                                        </div>
                                                        {nameOk(playerLabel) && (
                                                            <Button small look={'danger'} busy={busy} onClick={() => send('kick', playerLabel, '')}>
                                                                {t('players_kick')}
                                                            </Button>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </>
                                    )}
                                </>
                            )}
                            <form
                                className={'techtyl-tools-form'}
                                style={{ marginTop: 12 }}
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    send('kick', label, reason);
                                }}
                            >
                                <div className={'techtyl-tools-field'}>
                                    <label htmlFor={'techtyl-kick-player'}>{t('players_add_label')}</label>
                                    <input id={'techtyl-kick-player'} value={label} maxLength={17} autoComplete={'off'} onChange={(event) => setLabel(event.target.value)} />
                                </div>
                                <div className={'techtyl-tools-field'}>
                                    <label htmlFor={'techtyl-kick-reason'}>{t('players_reason')}</label>
                                    <input id={'techtyl-kick-reason'} value={reason} maxLength={100} autoComplete={'off'} onChange={(event) => setReason(event.target.value)} />
                                </div>
                                <Button look={'danger'} busy={busy} onClick={() => send('kick', label, reason)}>
                                    {t('players_kick')}
                                </Button>
                            </form>
                        </div>
                    )}

                    {tab === 'ips' && (
                        <div>
                            <Notice tone={'info'}>{t('players_ip_note')}</Notice>
                            {data.ip_bans.broken && <Notice tone={'warning'}>{t('players_broken')}</Notice>}
                            {data.ip_bans.too_large && <Notice tone={'warning'}>{t('players_too_large')}</Notice>}
                            {!data.ip_bans.broken && !data.ip_bans.too_large && data.ip_bans.entries.length === 0 && (
                                <p className={'techtyl-tools-muted'}>{t('players_empty_ips')}</p>
                            )}
                            {data.ip_bans.entries.map((row) => (
                                <div key={row.ip} className={'techtyl-tools-player'}>
                                    <div className={'techtyl-tools-player-main'}>
                                        <strong>{row.ip}</strong>
                                        {row.reason !== '' && <span className={'techtyl-tools-muted'}>{row.reason}</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {list && (
                        <div>
                            {list.broken && <Notice tone={'warning'}>{t('players_broken')}</Notice>}
                            {list.too_large && <Notice tone={'warning'}>{t('players_too_large')}</Notice>}
                            {!list.broken && !list.too_large && (
                                <form
                                    className={'techtyl-tools-form'}
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        const action: PlayerAction = tab === 'ops' ? 'op' : tab === 'bans' ? 'ban' : 'whitelist_add';
                                        send(action, label, reason);
                                    }}
                                >
                                    <div className={'techtyl-tools-field'}>
                                        <label htmlFor={'techtyl-player-label'}>{t('players_add_label')}</label>
                                        <input id={'techtyl-player-label'} value={label} maxLength={17} autoComplete={'off'} onChange={(event) => setLabel(event.target.value)} />
                                    </div>
                                    {tab === 'bans' && (
                                        <div className={'techtyl-tools-field'}>
                                            <label htmlFor={'techtyl-player-reason'}>{t('players_reason')}</label>
                                            <input id={'techtyl-player-reason'} value={reason} maxLength={100} autoComplete={'off'} placeholder={t('players_reason_hint')} onChange={(event) => setReason(event.target.value)} />
                                        </div>
                                    )}
                                    <Button look={'primary'} busy={busy} onClick={() => send(tab === 'ops' ? 'op' : tab === 'bans' ? 'ban' : 'whitelist_add', label, reason)}>
                                        {t(tab === 'ops' ? 'players_add_op' : tab === 'bans' ? 'players_add_ban' : 'players_add_whitelist')}
                                    </Button>
                                </form>
                            )}
                            {!list.broken && !list.too_large && list.entries.length === 0 && (
                                <p className={'techtyl-tools-muted'}>
                                    {t(tab === 'ops' ? 'players_empty_ops' : tab === 'bans' ? 'players_empty_bans' : 'players_empty_whitelist')}
                                </p>
                            )}
                            {list.entries.map((row) => {
                                const playerLabel = row.name;
                                const remove: PlayerAction = tab === 'ops' ? 'deop' : tab === 'bans' ? 'pardon' : 'whitelist_remove';

                                return (
                                    <div key={playerLabel + (row.uuid || '')} className={'techtyl-tools-player'}>
                                        <Head uuid={row.uuid} label={playerLabel} />
                                        <div className={'techtyl-tools-player-main'}>
                                            <strong>{playerLabel}</strong>
                                            {row.level != null && <span className={'techtyl-tools-muted'}>{t('players_level', { level: row.level })}</span>}
                                            {row.reason !== '' && <div className={'techtyl-tools-muted'}>{row.reason}</div>}
                                        </div>
                                        {rowActions(row, remove)}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_players')}>
            <ToolsGate tool={'players'}>{(meta, server) => <Page meta={meta} server={server} />}</ToolsGate>
        </ServerContentBlock>
    );
};
