import React, { useEffect, useRef, useState } from 'react';
import {
    explain,
    getSoftware,
    getSoftwareBuilds,
    getSoftwareVersions,
    previewSoftware,
    SoftwarePreview,
    SoftwareView,
    switchSoftware,
    ToolsMeta,
    ToolsTask,
    VersionEntry,
    BuildEntry,
} from './api';
import { useToolsT } from './text';
import { Bar, Button, cx, Dialog, Empty, formatDate, Icon, Notice, Spinner } from './ui';

const SOFTWARE_STEPS = ['prepare', 'stop', 'backup', 'move', 'configure', 'install'];

interface TaskBag {
    active: ToolsTask[];
    recent: ToolsTask[];
}

interface SoftwareProps {
    server: string;
    meta: ToolsMeta;
    tasks: TaskBag;
    note: ToolsTask | null;
    onClearNote: () => void;
    onTrack: (task: ToolsTask) => void;
}

const sameWord = (typed: string, word: string): boolean => typed.trim().toLowerCase() === word.trim().toLowerCase();

const Running = ({ task }: { task: ToolsTask }) => {
    const t = useToolsT();
    const steps = SOFTWARE_STEPS.filter((step) => step !== 'backup' || task.subject.backup);
    const index = task.step === '' ? 0 : steps.indexOf(task.step);
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
                {t('software_run_hint')}
            </p>
        </div>
    );
};

interface PreviewProps {
    server: string;
    loader: string;
    game: string;
    build: string;
    onClose: () => void;
    onStarted: (task: ToolsTask) => void;
}

const Preview = ({ server, loader, game, build, onClose, onStarted }: PreviewProps) => {
    const t = useToolsT();
    const [preview, setPreview] = useState<SoftwarePreview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [backup, setBackup] = useState(true);
    const [start, setStart] = useState(false);
    const [typed, setTyped] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let alive = true;
        previewSoftware(server, loader, game, build)
            .then((result) => {
                if (!alive) return;
                setPreview(result);
                setBackup(result.backup.possible);
                setStart(false);
            })
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, loader, game, build]);

    const confirm = async () => {
        if (!preview) return;
        setError(null);
        setBusy(true);
        try {
            onStarted(await switchSoftware(server, loader, game, build, typed.trim(), backup, start));
        } catch (problem) {
            setError(explain(problem));
            setBusy(false);
        }
    };

    const matched = !!preview && sameWord(typed, preview.confirm_word);
    const blocked = !preview || !preview.can_switch || !matched;

    return (
        <Dialog
            title={t('software_preview_title')}
            onClose={() => !busy && onClose()}
            wide
            footer={
                <>
                    <Button onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button look={'primary'} busy={busy} disabled={blocked} onClick={confirm}>
                        {t('software_preview_go')}
                    </Button>
                </>
            }
        >
            {error && !preview && (
                <Empty icon={'warn'} title={t('preview_failed')}>
                    {error}
                </Empty>
            )}
            {error && preview && <Notice tone={'danger'}>{error}</Notice>}
            {!error && !preview && <Spinner label={t('software_preview_loading')} />}
            {preview && (
                <>
                    <div className={'techtyl-tools-fromto'}>
                        <div>
                            <small>{t('now')}</small>
                            <b>{preview.from.loader_label}</b>
                            <span className={'techtyl-tools-muted'}>{preview.from.egg}</span>
                        </div>
                        <Icon id={'arrow'} size={22} />
                        <div>
                            <small>{t('after')}</small>
                            <b>
                                {preview.to.loader_label} {preview.to.game}
                            </b>
                            <span className={'techtyl-tools-muted'}>{preview.to.egg}</span>
                        </div>
                    </div>

                    {preview.warnings.map((warning, position) => (
                        <Notice key={position} tone={warning.level}>
                            {warning.text}
                        </Notice>
                    ))}

                    <dl className={'techtyl-tools-facts'}>
                        <dt>{t('software_fact_egg')}</dt>
                        <dd>
                            {preview.from.egg} → <b>{preview.to.egg}</b>
                        </dd>
                        <dt>{t('software_fact_nest')}</dt>
                        <dd>
                            {preview.from.nest || t('empty_value')} → <b>{preview.to.nest || t('empty_value')}</b>
                        </dd>
                        <dt>{t('software_fact_image')}</dt>
                        <dd>
                            {preview.image.keep || !preview.image.to
                                ? t('software_fact_image_keep', { image: preview.image.from })
                                : t('software_fact_image_change', { from: preview.image.from, to: preview.image.to, java: preview.image.java })}
                        </dd>
                        <dt>{t('software_fact_startup')}</dt>
                        <dd className={'techtyl-tools-mono techtyl-tools-software-startup'}>{preview.startup}</dd>
                        {preview.variables.length > 0 && (
                            <>
                                <dt>{t('software_fact_vars')}</dt>
                                <dd>
                                    {preview.variables.map((item) => (
                                        <div key={item.key}>
                                            {item.label}: {item.from || t('empty_value')} → <b>{item.to}</b>
                                        </div>
                                    ))}
                                </dd>
                            </>
                        )}
                        <dt>{t('fact_file')}</dt>
                        <dd>
                            {preview.moves.length === 0 && <div>{t('software_move_none')}</div>}
                            {preview.moves.map((move) => (
                                <div key={move.name}>{t('software_move_folder', { name: move.name })}</div>
                            ))}
                            <div>{t('software_move_jar', { jar: preview.jar })}</div>
                        </dd>
                    </dl>

                    <label className={cx('techtyl-tools-check', !preview.backup.possible && 'techtyl-tools-check-disabled')}>
                        <input type={'checkbox'} checked={backup} disabled={!preview.backup.possible || busy} onChange={(event) => setBackup(event.target.checked)} />
                        <span>
                            {t('software_opt_backup')}
                            <small>{preview.backup.possible ? t('software_opt_backup_text', { used: preview.backup.used, limit: preview.backup.limit }) : preview.backup.reason}</small>
                        </span>
                    </label>
                    <label className={cx('techtyl-tools-check', !preview.can_start && 'techtyl-tools-check-disabled')}>
                        <input type={'checkbox'} checked={start} disabled={!preview.can_start || busy} onChange={(event) => setStart(event.target.checked)} />
                        <span>
                            {t('software_opt_start')}
                            <small>{t(preview.can_start ? 'opt_start_text' : 'opt_start_no')}</small>
                        </span>
                    </label>
                    <label className={'techtyl-tools-software-field'} htmlFor={'techtyl-tools-software-confirm'}>
                        <span>{t('software_confirm_label', { word: preview.confirm_word })}</span>
                        <input
                            id={'techtyl-tools-software-confirm'}
                            type={'text'}
                            value={typed}
                            maxLength={32}
                            autoComplete={'off'}
                            spellCheck={false}
                            disabled={busy || !preview.can_switch}
                            aria-label={t('software_confirm_word')}
                            onChange={(event) => setTyped(event.target.value)}
                        />
                        <small className={'techtyl-tools-muted'}>{t('software_confirm_hint')}</small>
                    </label>
                </>
            )}
        </Dialog>
    );
};

const Outcome = ({ task, onClose }: { task: ToolsTask; onClose: () => void }) => {
    const t = useToolsT();
    const result = task.result || {};
    const moved = Array.isArray(result.moved) ? result.moved.filter((name) => typeof name === 'string') : [];
    if (task.status === 'done') {
        return (
            <Notice tone={'ok'} title={t('software_done')} action={<Button small look={'ghost'} onClick={onClose}>{t('close')}</Button>}>
                {t('software_done_text', { loader: String(result.loader || task.subject.loader || ''), game: String(result.game || task.subject.game || '') })}
                {result.image_changed ? ` ${t('done_image')}` : ''}
                {result.started ? ` ${t('done_started')}` : ''}
                {moved.length > 0 && <div>{t('software_undo_files', { names: moved.join(', ') })}</div>}
                {result.jar_backup && <div>{t('software_undo_jar', { file: String(result.jar_backup) })}</div>}
            </Notice>
        );
    }

    const changed = moved.length > 0 || !!result.jar_backup || !!result.egg_changed || !!result.install_started;
    return (
        <Notice tone={'danger'} title={t('software_undo_title')} action={<Button small look={'ghost'} onClick={onClose}>{t('close')}</Button>}>
            {task.error && <div>{task.error}</div>}
            {moved.length > 0 && <div>{t('software_undo_files', { names: moved.join(', ') })}</div>}
            {result.jar_backup && <div>{t('software_undo_jar', { file: String(result.jar_backup) })}</div>}
            {result.egg_changed && <div>{t('software_undo_egg', { from: String(result.egg_from || ''), to: String(result.egg_to || '') })}</div>}
            {result.image_changed && <div>{t('software_undo_image', { from: String(result.image_from || ''), to: String(result.image_to || '') })}</div>}
            {result.egg_changed && !result.install_started && <div>{t('software_undo_install')}</div>}
            {result.restarted ? <div>{t('software_undo_restarted')}</div> : changed && <div>{t('software_undo_offline')}</div>}
        </Notice>
    );
};

/** Software section on the versions page. Hidden by the parent when the server is not a Minecraft loader. */
const Software = ({ server, meta, tasks, note, onClearNote, onTrack }: SoftwareProps) => {
    const t = useToolsT();
    const canRead = !!meta.permissions.startup_read;
    const [view, setView] = useState<SoftwareView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [loader, setLoader] = useState<string | null>(null);
    const [versions, setVersions] = useState<VersionEntry[] | null>(null);
    const [game, setGame] = useState('');
    const [builds, setBuilds] = useState<BuildEntry[] | null>(null);
    const [build, setBuild] = useState('');
    const [pickError, setPickError] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const versionRequest = useRef(0);
    const buildRequest = useRef(0);

    useEffect(() => {
        if (!canRead) return;
        let alive = true;
        setError(null);
        getSoftware(server)
            .then((data) => alive && setView(data))
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, canRead, attempt, note && note.uuid]);

    useEffect(() => {
        if (!loader) return;
        const id = ++versionRequest.current;
        setVersions(null);
        setGame('');
        setBuilds(null);
        setBuild('');
        setPickError(null);
        getSoftwareVersions(server, loader)
            .then((data) => {
                if (id !== versionRequest.current) return;
                setVersions(data.versions);
                const stable = data.versions.find((entry) => entry.stable);
                setGame(stable ? stable.id : data.versions.length > 0 ? data.versions[0].id : '');
            })
            .catch((problem) => id === versionRequest.current && setPickError(explain(problem)));
    }, [server, loader]);

    useEffect(() => {
        if (!loader || !game) return;
        const id = ++buildRequest.current;
        setBuilds(null);
        setBuild('');
        getSoftwareBuilds(server, loader, game)
            .then((data) => {
                if (id !== buildRequest.current) return;
                setBuilds(data.builds);
                const stable = data.builds.find((entry) => entry.channel === 'stable');
                setBuild(stable ? stable.id : data.builds.length > 0 ? data.builds[0].id : '');
            })
            .catch((problem) => id === buildRequest.current && setPickError(explain(problem)));
    }, [server, loader, game]);

    const running = tasks.active.find((task) => task.kind === 'software.switch');
    const otherSwitch = tasks.active.some((task) => task.kind === 'version.switch');
    const history = tasks.recent.filter((task) => task.kind === 'software.switch').slice(0, 5);

    if (!canRead) {
        return (
            <div className={'techtyl-tools-panel techtyl-tools-software'}>
                <div className={'techtyl-tools-panel-title'}>
                    <h2>{t('software_title')}</h2>
                </div>
                <Notice tone={'warning'} title={t('software_locked')}>
                    {t('software_need_read')}
                </Notice>
            </div>
        );
    }
    if (error) {
        return (
            <div className={'techtyl-tools-panel techtyl-tools-software'}>
                <Empty icon={'warn'} title={t('software_failed')} action={<Button onClick={() => setAttempt((value) => value + 1)}>{t('retry')}</Button>}>
                    {error}
                </Empty>
            </div>
        );
    }
    if (!view) {
        return (
            <div className={'techtyl-tools-panel techtyl-tools-software'}>
                <Spinner label={t('software_loading')} />
            </div>
        );
    }
    if (!view.offered) return null;

    const picked = view.loaders.find((row) => row.id === loader);

    return (
        <>
            <div className={'techtyl-tools-panel techtyl-tools-software'}>
                <div className={'techtyl-tools-software-head'}>
                    <div>
                        <div className={'techtyl-tools-current-label'}>{t('software_current')}</div>
                        <div className={'techtyl-tools-current-value'}>
                            <span className={'techtyl-tools-tag techtyl-tools-tag-accent'}>{view.loader_label}</span>
                        </div>
                    </div>
                    <div className={'techtyl-tools-chips'}>
                        <span className={'techtyl-tools-chip'}>
                            {view.online === true ? t('software_online') : view.online === false ? t('software_offline') : t('software_state_unknown')}
                        </span>
                        <span className={'techtyl-tools-chip'}>{view.backup.possible ? t('software_backup_yes') : t('software_backup_no')}</span>
                    </div>
                </div>

                {view.locked && (
                    <Notice tone={'warning'} title={t('software_locked')}>
                        {view.locked_reason}
                    </Notice>
                )}

                {otherSwitch && !running && <Notice tone={'info'}>{t('switch_other_running')}</Notice>}

                {!view.locked && !running && !otherSwitch && (
                    <>
                        <h3 className={'techtyl-tools-software-pick'}>{t('software_pick')}</h3>
                        <div className={'techtyl-tools-software-grid'} role={'group'} aria-label={t('software_pick')}>
                            {view.loaders.map((row) => (
                                <button
                                    key={row.id}
                                    type={'button'}
                                    className={'techtyl-tools-loader'}
                                    aria-pressed={loader === row.id}
                                    disabled={!row.available}
                                    onClick={() => setLoader(row.id)}
                                >
                                    <strong>{row.label}</strong>
                                    <small>{row.blurb}</small>
                                    {!row.available && row.reason && <small>{row.reason}</small>}
                                </button>
                            ))}
                        </div>

                        {picked && picked.available && (
                            <>
                                <div className={'techtyl-tools-software-picks'}>
                                    <label className={'techtyl-tools-software-field'}>
                                        <span>{t('software_version')}</span>
                                        <select className={'techtyl-tools-select'} value={game} aria-label={t('software_version')} onChange={(event) => setGame(event.target.value)} disabled={!versions}>
                                            {!versions && <option value={''}>{t('software_loading')}</option>}
                                            {versions && versions.length === 0 && <option value={''}>{t('no_builds')}</option>}
                                            {versions &&
                                                versions.map((entry) => (
                                                    <option key={entry.id} value={entry.id}>
                                                        {entry.id}
                                                        {entry.stable ? '' : ` (${t('tag_pre')})`}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                    <label className={'techtyl-tools-software-field'}>
                                        <span>{t('software_build')}</span>
                                        <select className={'techtyl-tools-select'} value={build} aria-label={t('software_build')} onChange={(event) => setBuild(event.target.value)} disabled={!builds}>
                                            {!builds && <option value={''}>{t('software_loading')}</option>}
                                            {builds && builds.length === 0 && <option value={''}>{t('no_builds')}</option>}
                                            {builds &&
                                                builds.map((entry) => (
                                                    <option key={entry.id} value={entry.id}>
                                                        {entry.label}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                </div>
                                {pickError && <Notice tone={'danger'}>{pickError}</Notice>}
                                <div className={'techtyl-tools-pick-foot'}>
                                    <span className={'techtyl-tools-muted'}>{game && build ? `${picked.label} ${game}` : t('pick_hint')}</span>
                                    <Button look={'primary'} disabled={!game || !build} onClick={() => setOpen(true)}>
                                        {t('software_next')} <Icon id={'arrow'} />
                                    </Button>
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>

            {note && <Outcome task={note} onClose={onClearNote} />}
            {running && <Running task={running} />}

            {history.length > 0 && (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>{t('software_history')}</h2>
                    </div>
                    <div className={'techtyl-tools-history'}>
                        {history.map((task) => (
                            <div key={task.uuid} className={'techtyl-tools-history-row'}>
                                <span>
                                    {task.title}
                                    {task.status === 'failed' && task.error && (
                                        <small className={'techtyl-tools-muted'} style={{ display: 'block' }}>
                                            {task.error}
                                        </small>
                                    )}
                                </span>
                                <span className={'techtyl-tools-chips'}>
                                    <span className={'techtyl-tools-muted'}>{formatDate(task.finished_at)}</span>
                                    <span className={cx('techtyl-tools-tag', task.status === 'done' ? 'techtyl-tools-tag-ok' : 'techtyl-tools-tag-bad')}>
                                        {t(task.status === 'done' ? 'status_done' : 'status_failed')}
                                    </span>
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {open && loader && game && build && (
                <Preview
                    server={server}
                    loader={loader}
                    game={game}
                    build={build}
                    onClose={() => setOpen(false)}
                    onStarted={(task) => {
                        setOpen(false);
                        onTrack(task);
                    }}
                />
            )}
        </>
    );
};

export default Software;
