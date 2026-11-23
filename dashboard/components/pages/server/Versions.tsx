import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import {
    BuildEntry,
    explain,
    getBuilds,
    getVersions,
    previewSwitch,
    sendPower,
    switchVersion,
    ToolsMeta,
    ToolsTask,
    VersionList,
    VersionPreview,
} from '../../tools/api';
import { Translate, tt, useToolsT } from '../../tools/text';
import { Bar, Button, cx, Dialog, Empty, formatBytes, formatDate, Icon, Notice, SkeletonRows, Spinner, Toasts, ToolsGate, useServer, useTasks, useToasts } from '../../tools/ui';

const SOURCES = ['techtyl', 'files', 'variable', 'jar'];
const STEPS = ['backup', 'download', 'verify', 'stop', 'swap', 'configure'];

const describe = (label: string, ver: string, build: string | null, buildLabel: string, hasBuilds: boolean): string =>
    `${label} ${ver}${hasBuilds && build ? ` · ${buildLabel} ${build}` : ''}`;

const buildWord = (data: VersionList, t: Translate): string => t(data.type === 'fabric' ? 'col_loader' : 'col_build');

/* ------------------------------------------------------------------ Laufender Wechsel */

const Running = ({ task }: { task: ToolsTask }) => {
    const t = useToolsT();
    const steps = STEPS.filter((step) => step !== 'backup' || task.subject.backup);
    const index = steps.indexOf(task.step);
    const title = t('switch_title', { label: task.title });

    return (
        <div className={'techtyl-tools-panel'} aria-live={'polite'}>
            <div className={'techtyl-tools-running-head'}>
                <strong>{title}</strong>
                <span>{task.progress} %</span>
            </div>
            <Bar value={task.progress} label={title} />
            <ol className={'techtyl-tools-steps'}>
                {steps.map((step, position) => (
                    <li key={step} data-state={index === -1 ? 'todo' : position < index ? 'done' : position === index ? 'now' : 'todo'}>
                        {t(`run_${step}`)}
                    </li>
                ))}
            </ol>
            <p className={'techtyl-tools-muted'} style={{ marginTop: 10 }}>
                {t('run_hint')}
            </p>
        </div>
    );
};

/* ------------------------------------------------------------------ Zusammenfassung */

interface PreviewDialogProps {
    server: string;
    data: VersionList;
    ver: string;
    build: string;
    onClose: () => void;
    onStarted: (task: ToolsTask) => void;
    onError: (text: string) => void;
}

const PreviewDialog = ({ server, data, ver, build, onClose, onStarted, onError }: PreviewDialogProps) => {
    const t = useToolsT();
    const buildLabel = buildWord(data, t);
    const [preview, setPreview] = useState<VersionPreview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [backup, setBackup] = useState(false);
    const [start, setStart] = useState(false);
    const [understood, setUnderstood] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let alive = true;
        previewSwitch(server, ver, build)
            .then((result) => {
                if (!alive) return;
                setPreview(result);
                setBackup(result.backup.possible && result.direction !== 'build');
                setStart(result.server_state !== 'offline' && result.can_start);
            })
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, ver, build]);

    const confirm = async () => {
        setBusy(true);
        try {
            onStarted(await switchVersion(server, ver, build, backup, start));
        } catch (problem) {
            onError(explain(problem));
            setBusy(false);
        }
    };

    const risky = !!preview && preview.direction === 'downgrade' && !data.proxy;
    const blocked = !preview || !preview.can_stop || preview.direction === 'same' || (risky && !understood);

    return (
        <Dialog
            title={t('preview_title')}
            onClose={() => !busy && onClose()}
            footer={
                <>
                    <Button onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button look={risky ? 'danger' : 'primary'} busy={busy} disabled={blocked} onClick={confirm}>
                        {t('preview_go')}
                    </Button>
                </>
            }
        >
            {error && (
                <Empty icon={'warn'} title={t('preview_failed')}>
                    {error}
                </Empty>
            )}
            {!error && !preview && <Spinner label={t('preview_loading')} />}
            {preview && (
                <>
                    <div className={'techtyl-tools-fromto'}>
                        <div>
                            <small>{t('now')}</small>
                            <b>{preview.from.version || t('state_unknown')}</b>
                            {data.has_builds && preview.from.build && (
                                <span className={'techtyl-tools-muted'}>
                                    {buildLabel} {preview.from.build}
                                </span>
                            )}
                        </div>
                        <Icon id={'arrow'} size={22} />
                        <div>
                            <small>{t('after')}</small>
                            <b>{preview.to.version}</b>
                            {data.has_builds && (
                                <span className={'techtyl-tools-muted'}>
                                    {buildLabel} {preview.to.build}
                                </span>
                            )}
                        </div>
                    </div>

                    {preview.direction === 'same' && <Notice tone={'info'}>{t('same_version')}</Notice>}
                    {preview.warnings.map((warning, position) => (
                        <Notice key={position} tone={warning.level}>
                            {warning.text}
                        </Notice>
                    ))}

                    <dl className={'techtyl-tools-facts'}>
                        <dt>{t('fact_server')}</dt>
                        <dd>{t(preview.server_state === 'offline' ? 'fact_server_off' : 'fact_server_on')}</dd>
                        <dt>{t('fact_file')}</dt>
                        <dd>{t('fact_file_text', { jar: preview.jar, backup: preview.jar_backup })}</dd>
                        {preview.download_size && (
                            <>
                                <dt>{t('fact_download')}</dt>
                                <dd>{t('fact_download_text', { size: formatBytes(preview.download_size) })}</dd>
                            </>
                        )}
                        <dt>{t('fact_java')}</dt>
                        <dd>
                            {preview.java.new_image
                                ? t('fact_java_change', { java: preview.java.required, from: preview.java.current_image, to: preview.java.new_image })
                                : t('fact_java_keep', { java: preview.java.required, from: preview.java.current_image })}
                        </dd>
                        {preview.variables.length > 0 && (
                            <>
                                <dt>{t('fact_vars')}</dt>
                                <dd>
                                    {preview.variables.map((item) => (
                                        <div key={item.key}>
                                            {item.label}:{' '}
                                            {item.set ? (
                                                <>
                                                    {item.from || t('empty_value')} → <b>{item.to}</b>
                                                </>
                                            ) : (
                                                <span className={'techtyl-tools-muted'}>{t('var_stays', { value: item.from || t('empty_value'), reason: item.reason || '' })}</span>
                                            )}
                                        </div>
                                    ))}
                                </dd>
                            </>
                        )}
                    </dl>

                    <label className={cx('techtyl-tools-check', !preview.backup.possible && 'techtyl-tools-check-disabled')}>
                        <input type={'checkbox'} checked={backup} disabled={!preview.backup.possible || busy} onChange={(event) => setBackup(event.target.checked)} />
                        <span>
                            {t('opt_backup')}
                            <small>{preview.backup.possible ? t('opt_backup_text', { used: preview.backup.used, limit: preview.backup.limit }) : preview.backup.reason}</small>
                        </span>
                    </label>
                    <label className={cx('techtyl-tools-check', !preview.can_start && 'techtyl-tools-check-disabled')}>
                        <input type={'checkbox'} checked={start} disabled={!preview.can_start || busy} onChange={(event) => setStart(event.target.checked)} />
                        <span>
                            {t('opt_start')}
                            <small>{t(preview.can_start ? 'opt_start_text' : 'opt_start_no')}</small>
                        </span>
                    </label>
                    {risky && (
                        <label className={'techtyl-tools-check'}>
                            <input type={'checkbox'} checked={understood} disabled={busy} onChange={(event) => setUnderstood(event.target.checked)} />
                            <span>
                                {t('opt_risk')}
                                <small>{t('opt_risk_text')}</small>
                            </span>
                        </label>
                    )}
                </>
            )}
        </Dialog>
    );
};

/* ------------------------------------------------------------------ Seite */

const VersionsPage = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const { status } = useServer();
    const { toasts, push, dismiss } = useToasts();
    const t = useToolsT();
    const [data, setData] = useState<VersionList | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [filter, setFilter] = useState('');
    const [unstable, setUnstable] = useState(false);
    const [picked, setPicked] = useState<string | null>(null);
    const [builds, setBuilds] = useState<BuildEntry[] | null>(null);
    const [buildError, setBuildError] = useState<string | null>(null);
    const [build, setBuild] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const [finished, setFinished] = useState<ToolsTask | null>(null);
    const [starting, setStarting] = useState(false);
    const allowed = !!meta.permissions.version;

    const onFinished = useCallback(
        (task: ToolsTask) => {
            if (task.kind !== 'version.switch') return;
            if (task.status === 'done') {
                push('ok', tt('switch_done', { label: task.title }));
                setFinished(task);
            } else {
                push('danger', `${tt('switch_title', { label: task.title })}: ${task.error || tt('switch_failed')}`);
            }
            setAttempt((value) => value + 1);
        },
        [push]
    );
    const tasks = useTasks(server, onFinished);
    const running = tasks.active.find((task) => task.kind === 'version.switch');
    const history = tasks.recent.filter((task) => task.kind === 'version.switch').slice(0, 5);

    useEffect(() => {
        if (!allowed) return;
        let alive = true;
        setError(null);
        getVersions(server)
            .then((result) => {
                if (!alive) return;
                setData(result);
                setPicked((current) => {
                    if (current && result.versions.some((entry) => entry.id === current)) return current;
                    const own = result.current.version && result.versions.find((entry) => entry.id === result.current.version);
                    const firstStable = result.versions.find((entry) => entry.stable);

                    return own ? own.id : firstStable ? firstStable.id : result.versions.length > 0 ? result.versions[0].id : null;
                });
            })
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, attempt, allowed]);

    const buildRequest = useRef(0);
    useEffect(() => {
        if (!picked || !data || data.planned) return;
        const id = ++buildRequest.current;
        setBuilds(null);
        setBuild(null);
        setBuildError(null);
        getBuilds(server, picked)
            .then((list) => {
                if (id !== buildRequest.current) return;
                setBuilds(list);
                const stable = list.find((entry) => entry.channel === 'stable');
                setBuild(stable ? stable.id : list.length > 0 ? list[0].id : null);
            })
            .catch((problem) => id === buildRequest.current && setBuildError(explain(problem)));
    }, [server, picked, data && data.type, data && data.planned]);

    const visible = useMemo(() => {
        if (!data) return [];
        const needle = filter.trim().toLowerCase();

        return data.versions.filter((entry) => (unstable || entry.stable || entry.id === data.current.version) && (needle === '' || entry.id.toLowerCase().includes(needle)));
    }, [data, filter, unstable]);

    const groupSizes = useMemo(() => {
        const sizes: Record<string, number> = {};
        visible.forEach((entry) => {
            sizes[entry.group] = (sizes[entry.group] || 0) + 1;
        });

        return sizes;
    }, [visible]);

    const startServer = async () => {
        setStarting(true);
        try {
            await sendPower(server, 'start');
            push('ok', t('server_starting'));
            setFinished(null);
        } catch (problem) {
            push('danger', explain(problem, t('start_failed')));
        }
        setStarting(false);
    };

    if (!allowed) {
        return (
            <Empty icon={'info'} title={t('version_right_title')}>
                {t('version_right_text')}
            </Empty>
        );
    }
    if (error) {
        return (
            <Empty icon={'warn'} title={t('versions_failed')} action={<Button onClick={() => setAttempt(attempt + 1)}>{t('retry')}</Button>}>
                {error}
            </Empty>
        );
    }
    if (!data) {
        return (
            <div className={'techtyl-tools-panel'}>
                <SkeletonRows rows={5} />
            </div>
        );
    }

    const buildLabel = buildWord(data, t);
    const newestStable = data.versions.find((entry) => entry.stable);
    const isCurrent = !!picked && picked === data.current.version && (!data.has_builds || (!!build && build === data.current.build));
    const outdated = !data.proxy && newestStable && data.current.version && newestStable.id !== data.current.version && data.versions.some((entry) => entry.id === data.current.version);

    return (
        <>
            <div className={'techtyl-tools-panel'}>
                <div className={'techtyl-tools-current'}>
                    <div>
                        <div className={'techtyl-tools-current-label'}>{t('current_version')}</div>
                        <div className={'techtyl-tools-current-value'}>
                            {data.type_label} {data.current.version || t('state_unknown')}
                            {data.has_builds && data.current.build && (
                                <small>
                                    {buildLabel} {data.current.build}
                                </small>
                            )}
                        </div>
                        <p className={'techtyl-tools-muted'}>
                            {data.current.version ? `${t(SOURCES.includes(data.current.source || '') ? `source_${data.current.source}` : 'source_other')} · ` : `${t('current_unknown')} `}
                            {t('server_file')} <span className={'techtyl-tools-mono'}>{data.jar}</span>
                        </p>
                    </div>
                    <div className={'techtyl-tools-chips'}>
                        {outdated && newestStable && (
                            <span className={'techtyl-tools-tag techtyl-tools-tag-accent'}>
                                <Icon id={'up'} size={12} /> {t('newer_available', { version: newestStable.id })}
                            </span>
                        )}
                        <span className={'techtyl-tools-chip'}>
                            {t('server_chip')} <b>{t(status === 'running' || status === 'offline' || status === 'starting' || status === 'stopping' ? `state_${status}` : 'state_unknown')}</b>
                        </span>
                    </div>
                </div>
            </div>

            {finished && finished.result && (
                <Notice
                    tone={'ok'}
                    title={t('switch_done_title', { label: finished.title })}
                    action={
                        <>
                            {!finished.result.started && status === 'offline' && meta.permissions.start && (
                                <Button small look={'primary'} busy={starting} onClick={startServer}>
                                    <Icon id={'power'} /> {t('start_server')}
                                </Button>
                            )}
                            <Button small look={'ghost'} onClick={() => setFinished(null)}>
                                {t('close')}
                            </Button>
                        </>
                    }
                >
                    {t('done_backup', { file: finished.result.jar_backup })}
                    {finished.result.image_changed ? ` ${t('done_image')}` : ''}
                    {finished.result.started ? ` ${t('done_started')}` : ''}
                </Notice>
            )}

            {data.planned ? (
                <Notice tone={'info'} title={t('planned_title', { type: data.type_label })}>
                    {t('planned_text', { type: data.type_label })}
                </Notice>
            ) : running ? (
                <Running task={running} />
            ) : (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>{t('pick_title')}</h2>
                        {data.versions.some((entry) => !entry.stable) && (
                            <label className={'techtyl-tools-check'} style={{ padding: 0, border: 0 }}>
                                <input type={'checkbox'} checked={unstable} onChange={(event) => setUnstable(event.target.checked)} />
                                <span>{t('show_unstable')}</span>
                            </label>
                        )}
                    </div>

                    <div className={'techtyl-tools-pick'}>
                        <div className={'techtyl-tools-pick-col'}>
                            <h3>{t(data.proxy ? 'col_version' : 'col_mc_version')}</h3>
                            <div className={'techtyl-tools-search'} style={{ marginBottom: 8 }}>
                                <Icon id={'search'} size={18} />
                                <input type={'search'} value={filter} maxLength={30} onChange={(event) => setFilter(event.target.value)} placeholder={t('search_version')} aria-label={t('search_version_label')} autoComplete={'off'} />
                            </div>
                            <div className={'techtyl-tools-options'} role={'group'} aria-label={t('versions')}>
                                {visible.length === 0 && <p className={'techtyl-tools-group'}>{t('no_version_match', { query: filter })}</p>}
                                {visible.map((entry, position) => (
                                    <React.Fragment key={entry.id}>
                                        {groupSizes[entry.group] > 1 && (position === 0 || visible[position - 1].group !== entry.group) && <div className={'techtyl-tools-group'}>{entry.group}</div>}
                                        <button type={'button'} className={'techtyl-tools-option'} aria-pressed={picked === entry.id} onClick={() => setPicked(entry.id)}>
                                            <span className={'techtyl-tools-option-main'}>{entry.id}</span>
                                            <span className={'techtyl-tools-option-tags'}>
                                                {entry.id === data.current.version && <span className={'techtyl-tools-tag techtyl-tools-tag-ok'}>{t('tag_current')}</span>}
                                                {newestStable && entry.id === newestStable.id && entry.id !== data.current.version && <span className={'techtyl-tools-tag techtyl-tools-tag-accent'}>{t('tag_newest')}</span>}
                                                {!entry.stable && <span className={'techtyl-tools-tag techtyl-tools-tag-warn'}>{t('tag_pre')}</span>}
                                            </span>
                                        </button>
                                    </React.Fragment>
                                ))}
                            </div>
                        </div>

                        <div className={'techtyl-tools-pick-col'}>
                            <h3>{data.has_builds ? buildLabel : t('col_edition')}</h3>
                            {buildError ? (
                                <Notice tone={'danger'}>{buildError}</Notice>
                            ) : !builds ? (
                                <div className={'techtyl-tools-options'}>
                                    <p className={'techtyl-tools-group'}>
                                        <Spinner label={picked ? t('builds_loading', { version: picked }) : t('pick_left')} />
                                    </p>
                                </div>
                            ) : builds.length === 0 ? (
                                <Notice tone={'info'}>{t('no_builds')}</Notice>
                            ) : (
                                <div className={'techtyl-tools-options'} role={'group'} aria-label={buildLabel}>
                                    {builds.map((entry, position) => (
                                        <button key={entry.id} type={'button'} className={'techtyl-tools-option'} aria-pressed={build === entry.id} onClick={() => setBuild(entry.id)}>
                                            <span className={'techtyl-tools-option-main'}>
                                                {entry.label}
                                                <small>
                                                    {[entry.time ? formatDate(entry.time) : '', entry.size ? formatBytes(entry.size) : ''].filter(Boolean).join(' · ')}
                                                </small>
                                            </span>
                                            <span className={'techtyl-tools-option-tags'}>
                                                {picked === data.current.version && entry.id === data.current.build && <span className={'techtyl-tools-tag techtyl-tools-tag-ok'}>{t('tag_current')}</span>}
                                                {position === 0 && data.has_builds && <span className={'techtyl-tools-tag techtyl-tools-tag-accent'}>{t('tag_newest')}</span>}
                                                {entry.channel !== 'stable' && <span className={'techtyl-tools-tag techtyl-tools-tag-warn'}>{entry.channel === 'alpha' ? 'Alpha' : entry.channel === 'beta' ? 'Beta' : entry.channel}</span>}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className={'techtyl-tools-pick-foot'}>
                        <span className={'techtyl-tools-muted'}>
                            {picked && build
                                ? isCurrent
                                    ? t('picked_current')
                                    : t('picked', { label: describe(data.type_label, picked, build, buildLabel, data.has_builds) })
                                : t('pick_hint')}
                        </span>
                        <Button look={'primary'} disabled={!picked || !build || isCurrent} onClick={() => setOpen(true)}>
                            {t('next')} <Icon id={'arrow'} />
                        </Button>
                    </div>
                </div>
            )}

            {history.length > 0 && (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>{t('history')}</h2>
                    </div>
                    <div className={'techtyl-tools-history'}>
                        {history.map((task) => (
                            <div key={task.uuid} className={'techtyl-tools-history-row'}>
                                <span>
                                    {task.title}
                                    {task.status === 'failed' && task.error && <small className={'techtyl-tools-muted'} style={{ display: 'block' }}>{task.error}</small>}
                                </span>
                                <span className={'techtyl-tools-chips'}>
                                    <span className={'techtyl-tools-muted'}>{formatDate(task.finished_at)}</span>
                                    <span className={cx('techtyl-tools-tag', task.status === 'done' ? 'techtyl-tools-tag-ok' : 'techtyl-tools-tag-bad')}>{t(task.status === 'done' ? 'status_done' : 'status_failed')}</span>
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {open && picked && build && (
                <PreviewDialog
                    server={server}
                    data={data}
                    ver={picked}
                    build={build}
                    onClose={() => setOpen(false)}
                    onStarted={(task) => {
                        setOpen(false);
                        setFinished(null);
                        tasks.track(task);
                    }}
                    onError={(text) => push('danger', text)}
                />
            )}

            <Toasts toasts={toasts} dismiss={dismiss} />
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_versions')}>
            <ToolsGate tool={'versions'}>{(meta, server) => <VersionsPage meta={meta} server={server} />}</ToolsGate>
        </ServerContentBlock>
    );
};
