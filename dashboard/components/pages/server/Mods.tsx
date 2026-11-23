import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import {
    ContentDependency,
    ContentFilters,
    ContentHit,
    ContentProject,
    ContentUpdates,
    ContentVersion,
    explain,
    getDependencies,
    getFilters,
    getInstalled,
    getProject,
    getProjectVersions,
    getUpdates,
    installContent,
    InstalledItem,
    removeContent,
    searchContent,
    sendPower,
    ToolsMeta,
    ToolsTask,
    updateContent,
} from '../../tools/api';
import {
    Bar,
    Button,
    cx,
    Dialog,
    Empty,
    formatBytes,
    formatCount,
    formatDate,
    Icon,
    Notice,
    ProjectIcon,
    Segmented,
    SkeletonRows,
    Spinner,
    Toasts,
    ToolsGate,
    useServer,
    useTasks,
    useToasts,
} from '../../tools/ui';
import { SafeMarkdown } from '../../tools/markdown';
import { tt, useToolsT } from '../../tools/text';

type Tab = 'browse' | 'installed';
type View = 'list' | 'cards';

interface InstallRequest {
    projectId: string;
    title: string;
    versionId?: string;
    requiredCount?: number;
}

interface DependencyPrompt {
    projectId: string;
    title: string;
    versionId: string;
    dependencies: ContentDependency[];
}

const VIEW_KEY = 'techtyl:tools:view';
const RESTART_KEY = 'techtyl:tools:restart:';

const readStored = (key: string): string | null => {
    try {
        return window.localStorage.getItem(key);
    } catch (error) {
        return null;
    }
};

const writeStored = (key: string, value: string | null) => {
    try {
        if (value === null) window.localStorage.removeItem(key);
        else window.localStorage.setItem(key, value);
    } catch (error) {
        // Ohne Speicher gilt die Auswahl nur bis zum Neuladen.
    }
};

const CHANNEL: Record<string, string> = { beta: 'Beta', alpha: 'Alpha' };

const gameVersionText = (ver: ContentVersion): string => {
    const list = ver.game_versions;
    if (list.length === 0) return '';
    if (ver.game_version_count > list.length || list.length > 3) return tt('mc_range', { from: list[0], to: list[list.length - 1] });

    return list.join(', ');
};

/* ------------------------------------------------------------------ Projektansicht */

interface ProjectDialogProps {
    server: string;
    projectId: string;
    kindLabel: string;
    canInstall: boolean;
    task: ToolsTask | undefined;
    onClose: () => void;
    onInstall: (request: InstallRequest) => void;
}

const ProjectDialog = ({ server, projectId, kindLabel, canInstall, task, onClose, onInstall }: ProjectDialogProps) => {
    const t = useToolsT();
    const [project, setProject] = useState<ContentProject | null>(null);
    const [versions, setVersions] = useState<ContentVersion[] | null>(null);
    const [gameVersion, setGameVersion] = useState<string | null>(null);
    const [installedId, setInstalledId] = useState<string | null>(null);
    const [all, setAll] = useState(false);
    const [section, setSection] = useState<'about' | 'versions'>('about');
    const [error, setError] = useState<string | null>(null);
    const [versionError, setVersionError] = useState<string | null>(null);

    useEffect(() => {
        let alive = true;
        getProject(server, projectId)
            .then((data) => {
                if (!alive) return;
                setProject(data);
                setInstalledId(data.installed_version_id);
            })
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, projectId]);

    useEffect(() => {
        let alive = true;
        setVersions(null);
        setVersionError(null);
        getProjectVersions(server, projectId, all)
            .then((data) => {
                if (!alive) return;
                setVersions(data.versions);
                setGameVersion(data.game_version);
                setInstalledId(data.installed_version_id);
            })
            .catch((problem) => alive && setVersionError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, projectId, all, task && task.status]);

    const newest = versions ? versions.find((item) => item.installable && item.channel === 'release') || versions.find((item) => item.installable) : undefined;
    const busy = !!task;

    const footer = (
        <>
            {busy && task && (
                <span className={'techtyl-tools-item-progress'}>
                    {t('step_progress', { step: task.step_label || t('waiting'), n: task.progress })}
                </span>
            )}
            <Button onClick={onClose}>{t('close')}</Button>
            {canInstall && newest && installedId !== newest.id && (
                <Button
                    look={'primary'}
                    busy={busy}
                    onClick={() => project && onInstall({ projectId, title: project.title, versionId: newest.id, requiredCount: newest.required_count })}
                >
                    <Icon id={installedId ? 'up' : 'download'} />
                    {installedId ? t('switch_to_version', { version: newest.number }) : t('install_version', { version: newest.number })}
                </Button>
            )}
        </>
    );

    return (
        <Dialog title={project ? project.title : kindLabel} onClose={onClose} wide footer={footer}>
            {error && (
                <Empty icon={'warn'} title={t('project_failed')}>
                    {error}
                </Empty>
            )}
            {!error && !project && <SkeletonRows rows={3} />}
            {project && (
                <>
                    <div className={'techtyl-tools-project-head'}>
                        <ProjectIcon url={project.icon_url} title={project.title} size={64} />
                        <div>
                            <p>{project.description}</p>
                            <div className={'techtyl-tools-item-meta'}>
                                <span>
                                    <Icon id={'download'} size={14} /> {t('downloads', { n: formatCount(project.downloads) })}
                                </span>
                                <span>
                                    <Icon id={'heart'} size={14} /> {formatCount(project.follows)}
                                </span>
                                {project.updated_at && (
                                    <span>
                                        <Icon id={'clock'} size={14} /> {t('updated_when', { when: formatDate(project.updated_at) })}
                                    </span>
                                )}
                                {project.license && <span>{t('license', { license: project.license })}</span>}
                            </div>
                            <div className={'techtyl-tools-project-links'}>
                                <a href={project.page_url} target={'_blank'} rel={'noopener noreferrer'}>
                                    <Icon id={'external'} size={14} /> {t('on_modrinth')}
                                </a>
                                {project.links.map((link) => (
                                    <a key={link.label} href={link.url} target={'_blank'} rel={'noopener noreferrer nofollow'}>
                                        {link.label}
                                    </a>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className={'techtyl-tools-project-tabs'}>
                        <Segmented
                            label={t('section')}
                            value={section}
                            onChange={setSection}
                            options={[
                                { id: 'about', label: t('description') },
                                {
                                    id: 'versions',
                                    label: (
                                        <>
                                            {t('versions')} {versions && <span className={'techtyl-tools-count'}>{versions.length}</span>}
                                        </>
                                    ),
                                },
                            ]}
                        />
                        {section === 'versions' && gameVersion && (
                            <label className={'techtyl-tools-check'} style={{ padding: 0, border: 0 }}>
                                <input type={'checkbox'} checked={all} onChange={(event) => setAll(event.target.checked)} />
                                <span>{t('show_other_versions')}</span>
                            </label>
                        )}
                    </div>

                    {section === 'about' &&
                        (project.body.trim() ? <SafeMarkdown source={project.body} /> : <p className={'techtyl-tools-muted'}>{t('no_body')}</p>)}

                    {section === 'versions' && (
                        <>
                            {versionError && <Notice tone={'danger'}>{versionError}</Notice>}
                            {!versionError && !versions && <SkeletonRows rows={4} />}
                            {versions && versions.length === 0 && (
                                <Empty title={gameVersion && !all ? t('no_version_title', { version: gameVersion }) : t('no_version_any')}>
                                    {gameVersion && !all ? t('no_version_text') : t('no_version_type')}
                                </Empty>
                            )}
                            {versions && versions.length > 0 && (
                                <div className={'techtyl-tools-versions'}>
                                    {versions.map((item) => (
                                        <div key={item.id} className={'techtyl-tools-version'}>
                                            <div>
                                                <div className={'techtyl-tools-version-title'}>
                                                    {item.number || item.title}
                                                    {CHANNEL[item.channel] && <span className={'techtyl-tools-tag techtyl-tools-tag-warn'}>{CHANNEL[item.channel]}</span>}
                                                    {item.matches_server === false && (
                                                        <span className={'techtyl-tools-tag techtyl-tools-tag-bad'} title={t('other_mc_title')}>
                                                            {t('other_mc')}
                                                        </span>
                                                    )}
                                                    {installedId === item.id && <span className={'techtyl-tools-tag techtyl-tools-tag-ok'}>{t('tag_installed')}</span>}
                                                </div>
                                                <small>
                                                    Minecraft {gameVersionText(item)} · {item.loaders.join(', ')} · {formatBytes(item.size)} · {formatDate(item.published_at)}
                                                    {item.required_count > 0 ? ` · ${item.required_count === 1 ? t('needs_one') : t('needs_many', { n: item.required_count })}` : ''}
                                                </small>
                                            </div>
                                            {canInstall && item.installable && installedId !== item.id && (
                                                <Button
                                                    small
                                                    disabled={busy}
                                                    onClick={() => onInstall({ projectId, title: project.title, versionId: item.id, requiredCount: item.required_count })}
                                                >
                                                    {installedId ? t('switch_to') : t('install')}
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </>
            )}
        </Dialog>
    );
};

/* ------------------------------------------------------------------ Abhaengigkeiten */

interface DependencyDialogProps {
    prompt: DependencyPrompt;
    kindWord: string;
    onClose: () => void;
    onConfirm: (selected: ContentDependency[]) => void;
}

const DependencyDialog = ({ prompt, kindWord, onClose, onConfirm }: DependencyDialogProps) => {
    const t = useToolsT();
    const missing = prompt.dependencies.filter((item) => !item.installed);
    const [picked, setPicked] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};
        missing.forEach((item) => {
            initial[item.project_id] = !!item.version && item.version.installable;
        });

        return initial;
    });
    const selected = missing.filter((item) => picked[item.project_id] && item.version && item.version.installable);

    return (
        <Dialog
            title={t('deps_title', { title: prompt.title, kinds: kindWord })}
            onClose={onClose}
            footer={
                <>
                    <Button onClick={onClose}>{t('cancel')}</Button>
                    <Button look={'primary'} onClick={() => onConfirm(selected)}>
                        <Icon id={'download'} />
                        {selected.length > 0 ? t('deps_install', { n: selected.length + 1 }) : t('deps_only', { title: prompt.title })}
                    </Button>
                </>
            }
        >
            <p className={'techtyl-tools-muted'} style={{ marginBottom: 8 }}>
                {t('deps_text', { title: prompt.title })}
            </p>
            {missing.map((item) => {
                const possible = !!item.version && item.version.installable;

                return (
                    <label key={item.project_id} className={cx('techtyl-tools-check', !possible && 'techtyl-tools-check-disabled')}>
                        <input
                            type={'checkbox'}
                            disabled={!possible}
                            checked={!!picked[item.project_id] && possible}
                            onChange={(event) => setPicked({ ...picked, [item.project_id]: event.target.checked })}
                        />
                        <ProjectIcon url={item.icon_url} title={item.title} size={36} />
                        <span>
                            <strong>{item.title}</strong>{' '}
                            {item.version && <span className={'techtyl-tools-muted'}>{item.version.number}</span>}
                            <small>
                                {possible
                                    ? item.version!.matches_server === false
                                        ? t('deps_not_released')
                                        : item.description
                                    : t('deps_none')}
                            </small>
                        </span>
                    </label>
                );
            })}
        </Dialog>
    );
};

/* ------------------------------------------------------------------ Seite */

const ContentPage = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const { status } = useServer();
    const { toasts, push, dismiss } = useToasts();
    const t = useToolsT();
    const tool = meta.tools.content;
    const isMod = tool.kind === 'mod';
    const plural = t(isMod ? 'mods' : 'plugins');
    const singular = t(isMod ? 'mod' : 'plugin');
    const can = meta.permissions;

    const [tab, setTab] = useState<Tab>('browse');
    const [view, setView] = useState<View>(() => (readStored(VIEW_KEY) === 'cards' ? 'cards' : 'list'));
    const [filters, setFilters] = useState<ContentFilters | null>(null);

    const [input, setInput] = useState('');
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState('');
    const [categories, setCategories] = useState<string[]>([]);
    const [anyVersion, setAnyVersion] = useState(false);

    const [hits, setHits] = useState<ContentHit[] | null>(null);
    const [total, setTotal] = useState(0);
    const [pages, setPages] = useState(1);
    const [page, setPage] = useState(0);
    const [searchedVersion, setSearchedVersion] = useState<string | null>(meta.version);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);

    const [installed, setInstalled] = useState<InstalledItem[] | null>(null);
    const [installedError, setInstalledError] = useState<string | null>(null);
    const [updates, setUpdates] = useState<ContentUpdates | null>(null);
    const [checking, setChecking] = useState(false);

    const [detail, setDetail] = useState<string | null>(null);
    const [prompt, setPrompt] = useState<DependencyPrompt | null>(null);
    const [resolving, setResolving] = useState<Record<string, boolean>>({});
    const [removing, setRemoving] = useState<InstalledItem | null>(null);
    const [removeBusy, setRemoveBusy] = useState(false);
    const [flash, setFlash] = useState<Record<string, boolean>>({});
    const [restart, setRestart] = useState<boolean>(() => readStored(RESTART_KEY + server) === '1');
    const [restarting, setRestarting] = useState(false);

    const loadInstalled = useCallback(
        (withUpdates: boolean) => {
            if (!can.read) return;
            getInstalled(server)
                .then((data) => {
                    setInstalled(data.items);
                    setInstalledError(null);
                })
                .catch((problem) => setInstalledError(explain(problem)));
            if (withUpdates) {
                setChecking(true);
                getUpdates(server)
                    .then(setUpdates)
                    .catch(() => setUpdates(null))
                    .then(() => setChecking(false));
            }
        },
        [server, can.read]
    );

    const needRestart = useCallback(() => {
        setRestart(true);
        writeStored(RESTART_KEY + server, '1');
    }, [server]);

    const onFinished = useCallback(
        (task: ToolsTask) => {
            if (task.kind !== 'content.install') return;
            if (task.status === 'done') {
                const result = task.result || {};
                push('ok', tt(result.updated ? 'done_updated' : 'done_installed', { title: task.title, version: result.version_number || '' }).replace(/\s+/g, ' '));
                const projectId = task.subject.project_id as string;
                setHits((list) => (list ? list.map((hit) => (hit.project_id === projectId ? { ...hit, installed: true } : hit)) : list));
                setFlash((current) => ({ ...current, [projectId]: true }));
                if (result.restart_required) needRestart();
            } else {
                push('danger', `${task.title}: ${task.error || tt('install_failed')}`);
            }
            loadInstalled(true);
        },
        [push, loadInstalled, needRestart]
    );

    const tasks = useTasks(server, onFinished);
    const { track } = tasks;
    const taskByProject = useMemo(() => {
        const map: Record<string, ToolsTask> = {};
        tasks.active.forEach((task) => {
            if (task.kind === 'content.install' && task.subject.project_id) map[task.subject.project_id] = task;
        });

        return map;
    }, [tasks.active]);

    // Filter (Kategorien, Sortierungen) einmal laden.
    useEffect(() => {
        if (!can.read) return;
        let alive = true;
        getFilters(server)
            .then((data) => alive && setFilters(data))
            .catch(() => undefined);
        loadInstalled(true);

        return () => {
            alive = false;
        };
    }, [server, can.read, loadInstalled]);

    // Eingabe verzoegert uebernehmen, damit nicht jeder Tastendruck sucht.
    useEffect(() => {
        const timer = window.setTimeout(() => setQuery(input.trim()), 320);

        return () => window.clearTimeout(timer);
    }, [input]);

    const requestId = useRef(0);
    const runSearch = useCallback(
        (targetPage: number) => {
            if (!can.read) return;
            const id = ++requestId.current;
            setSearching(true);
            setSearchError(null);
            searchContent(server, { query, sort, page: targetPage, categories, anyVersion })
                .then((data) => {
                    if (id !== requestId.current) return;
                    setHits((current) => (targetPage > 0 && current ? [...current, ...data.hits.filter((hit) => !current.some((old) => old.project_id === hit.project_id))] : data.hits));
                    setTotal(data.total);
                    setPages(data.pages);
                    setPage(targetPage);
                    setSearchedVersion(data.game_version);
                    setSearching(false);
                })
                .catch((problem) => {
                    if (id !== requestId.current) return;
                    setSearchError(explain(problem));
                    setSearching(false);
                });
        },
        [server, query, sort, categories, anyVersion, can.read]
    );

    useEffect(() => {
        setHits(null);
        runSearch(0);
    }, [runSearch, attempt]);

    // Der Neustart-Hinweis erledigt sich, sobald der Server neu startet oder aus ist.
    const lastStatus = useRef(status);
    useEffect(() => {
        if (restart && lastStatus.current === 'running' && status && status !== 'running') {
            setRestart(false);
            writeStored(RESTART_KEY + server, null);
        }
        if (restart && status === 'offline') {
            setRestart(false);
            writeStored(RESTART_KEY + server, null);
        }
        lastStatus.current = status;
    }, [status, restart, server]);

    const queueInstall = useCallback(
        async (items: { projectId: string; versionId: string; title: string }[]) => {
            for (let i = 0; i < items.length; i++) {
                try {
                    track(await installContent(server, items[i].projectId, items[i].versionId));
                } catch (problem) {
                    push('danger', `${items[i].title}: ${explain(problem)}`);
                }
            }
        },
        [server, track, push]
    );

    /** Installieren aus Liste oder Projektansicht: Version waehlen, Abhaengigkeiten klaeren, starten. */
    const beginInstall = useCallback(
        async (request: InstallRequest) => {
            if (resolving[request.projectId] || taskByProject[request.projectId]) return;
            setResolving((current) => ({ ...current, [request.projectId]: true }));
            try {
                let versionId = request.versionId;
                let requiredCount = request.requiredCount || 0;
                if (!versionId) {
                    const list = await getProjectVersions(server, request.projectId, false);
                    const pick = list.versions.find((item) => item.installable && item.channel === 'release') || list.versions.find((item) => item.installable);
                    if (!pick) {
                        push('info', tt('no_version_for', { what: list.game_version ? tt('minecraft_x', { version: list.game_version }) : tt('this_server'), title: request.title }));
                        setDetail(request.projectId);

                        return;
                    }
                    versionId = pick.id;
                    requiredCount = pick.required_count;
                }
                if (requiredCount > 0) {
                    const dependencies = await getDependencies(server, versionId);
                    if (dependencies.some((item) => !item.installed)) {
                        setPrompt({ projectId: request.projectId, title: request.title, versionId, dependencies });

                        return;
                    }
                }
                await queueInstall([{ projectId: request.projectId, versionId, title: request.title }]);
            } catch (problem) {
                push('danger', `${request.title}: ${explain(problem)}`);
            } finally {
                setResolving((current) => ({ ...current, [request.projectId]: false }));
            }
        },
        [server, resolving, taskByProject, queueInstall, push]
    );

    const confirmDependencies = (selected: ContentDependency[]) => {
        if (!prompt) return;
        const items = selected.map((item) => ({ projectId: item.project_id, versionId: item.version!.id, title: item.title }));
        items.push({ projectId: prompt.projectId, versionId: prompt.versionId, title: prompt.title });
        setPrompt(null);
        queueInstall(items);
    };

    const runUpdate = async (item: InstalledItem) => {
        try {
            track(await updateContent(server, item.filename));
        } catch (problem) {
            push('danger', `${item.title || item.filename}: ${explain(problem)}`);
        }
    };

    const confirmRemove = async () => {
        if (!removing) return;
        setRemoveBusy(true);
        try {
            await removeContent(server, removing.filename);
            push('ok', t('removed', { title: removing.title || removing.filename }));
            if (removing.project_id) {
                const projectId = removing.project_id;
                setHits((list) => (list ? list.map((hit) => (hit.project_id === projectId ? { ...hit, installed: false } : hit)) : list));
            }
            if (status && status !== 'offline') needRestart();
            setRemoving(null);
            loadInstalled(false);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setRemoveBusy(false);
    };

    const restartNow = async () => {
        setRestarting(true);
        try {
            await sendPower(server, 'restart');
            setRestart(false);
            writeStored(RESTART_KEY + server, null);
            push('ok', t('restart_started'));
        } catch (problem) {
            push('danger', explain(problem, t('restart_failed')));
        }
        setRestarting(false);
    };

    const changeView = (next: View) => {
        setView(next);
        writeStored(VIEW_KEY, next);
    };

    const toggleCategory = (id: string) =>
        setCategories((current) => (current.includes(id) ? current.filter((item) => item !== id) : current.length >= 5 ? current : [...current, id]));

    if (!can.read) {
        return (
            <Empty icon={'info'} title={t('read_right_title')}>
                {t('read_right_text', { kinds: plural })}
            </Empty>
        );
    }

    const updateCount = updates ? Object.keys(updates.updates).filter((file) => installed && installed.some((item) => item.filename === file)).length : 0;
    const versionLabel = meta.type === 'velocity' ? meta.type_label : `${meta.type_label}${meta.version ? ` ${meta.version}` : ''}`;

    const sideFor = (hit: ContentHit) => {
        const task = taskByProject[hit.project_id];
        if (task) {
            return (
                <>
                    <Bar value={task.status === 'pending' ? 2 : task.progress} label={t('installing_label', { title: hit.title })} />
                    <span className={'techtyl-tools-item-progress'}>
                        {task.status === 'pending' ? t('waiting_slot') : t('step_progress', { step: task.step_label || t('loading'), n: task.progress })}
                    </span>
                </>
            );
        }
        if (hit.installed) {
            return (
                <Button small disabled className={cx(flash[hit.project_id] && 'techtyl-tools-done-flash')}>
                    <Icon id={'check'} /> {t('installed')}
                </Button>
            );
        }
        if (!can.install) {
            return <span className={'techtyl-tools-item-progress'}>{t('no_install_right')}</span>;
        }

        return (
            <Button small look={'primary'} busy={!!resolving[hit.project_id]} onClick={() => beginInstall({ projectId: hit.project_id, title: hit.title })}>
                {!resolving[hit.project_id] && <Icon id={'download'} />} {t('install')}
            </Button>
        );
    };

    return (
        <>
            <div className={'techtyl-tools-top'}>
                <p>{t('intro', { label: versionLabel, directory: tool.directory || '' })}</p>
                <Segmented
                    label={t('tab_view')}
                    value={tab}
                    onChange={setTab}
                    options={[
                        { id: 'browse', label: t('tab_browse') },
                        {
                            id: 'installed',
                            label: (
                                <>
                                    {t('tab_installed')}
                                    {installed && <span className={cx('techtyl-tools-count', updateCount > 0 && 'techtyl-tools-count-hot')}>{updateCount > 0 ? t('updates_badge', { n: updateCount }) : installed.length}</span>}
                                </>
                            ),
                        },
                    ]}
                />
            </div>

            {restart && status !== 'offline' && (
                <Notice
                    tone={'warning'}
                    title={t('restart_title')}
                    action={
                        <>
                            {can.restart && (
                                <Button small look={'primary'} busy={restarting} onClick={restartNow}>
                                    <Icon id={'power'} /> {t('restart_now')}
                                </Button>
                            )}
                            <Button
                                small
                                look={'ghost'}
                                onClick={() => {
                                    setRestart(false);
                                    writeStored(RESTART_KEY + server, null);
                                }}
                            >
                                {t('later')}
                            </Button>
                        </>
                    }
                >
                    {t('restart_text', { kinds: plural })}
                    {!can.restart && ` ${t('restart_no_right')}`}
                </Notice>
            )}

            {meta.type !== 'velocity' && !meta.version && (
                <Notice tone={'info'} title={t('version_unknown_title')}>
                    {t('version_unknown_text', { kinds: plural })}
                </Notice>
            )}

            {tab === 'browse' && (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-toolbar'}>
                        <div className={'techtyl-tools-search'}>
                            <Icon id={'search'} size={18} />
                            <input
                                type={'search'}
                                value={input}
                                maxLength={80}
                                onChange={(event) => setInput(event.target.value)}
                                placeholder={t(isMod ? 'search_mod' : 'search_plugin')}
                                aria-label={t(isMod ? 'search_mod' : 'search_plugin')}
                                autoComplete={'off'}
                                spellCheck={false}
                            />
                            {input !== '' && (
                                <button type={'button'} className={'techtyl-tools-x'} onClick={() => setInput('')} aria-label={t('search_clear')}>
                                    <Icon id={'close'} size={14} />
                                </button>
                            )}
                        </div>
                        <select className={'techtyl-tools-select'} value={sort} onChange={(event) => setSort(event.target.value)} aria-label={t('sort')}>
                            <option value={''}>{t('sort_auto')}</option>
                            {(filters ? filters.sorts : []).map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <Segmented
                            label={t('layout')}
                            value={view}
                            onChange={changeView}
                            options={[
                                { id: 'list', label: <Icon id={'list'} />, title: t('layout_list') },
                                { id: 'cards', label: <Icon id={'grid'} />, title: t('layout_cards') },
                            ]}
                        />
                    </div>

                    <div className={'techtyl-tools-cats'} role={'group'} aria-label={t('filters')}>
                        {meta.version && meta.type !== 'velocity' && (
                            <button type={'button'} className={'techtyl-tools-cat'} aria-pressed={!anyVersion} onClick={() => setAnyVersion(!anyVersion)} title={t('only_for_title')}>
                                {t('only_for', { version: meta.version })}
                            </button>
                        )}
                        {(filters ? filters.categories : []).map((category) => (
                            <button key={category.id} type={'button'} className={'techtyl-tools-cat'} aria-pressed={categories.includes(category.id)} onClick={() => toggleCategory(category.id)}>
                                {category.label}
                            </button>
                        ))}
                    </div>

                    {searchError ? (
                        <Empty icon={'warn'} title={t('search_failed')} action={<Button onClick={() => setAttempt(attempt + 1)}>{t('retry')}</Button>}>
                            {searchError}
                        </Empty>
                    ) : hits === null ? (
                        <SkeletonRows rows={7} cards={view === 'cards'} />
                    ) : hits.length === 0 ? (
                        <Empty
                            icon={'search'}
                            title={query ? t('nothing_for', { query }) : t(isMod ? 'nothing_mods' : 'nothing_plugins')}
                            action={
                                (categories.length > 0 || (!anyVersion && !!searchedVersion)) && (
                                    <Button
                                        onClick={() => {
                                            setCategories([]);
                                            setAnyVersion(true);
                                        }}
                                    >
                                        {t('clear_filters')}
                                    </Button>
                                )
                            }
                        >
                            {!anyVersion && searchedVersion
                                ? t('nothing_hint_version', { version: searchedVersion, type: meta.type_label, kinds: plural })
                                : t('nothing_hint')}
                        </Empty>
                    ) : (
                        <>
                            <div className={'techtyl-tools-resultline'} aria-live={'polite'}>
                                <span>{searchedVersion ? t('hits_for', { n: formatCount(total), version: searchedVersion }) : t('hits', { n: formatCount(total) })}</span>
                                {searching && <Spinner />}
                            </div>
                            <div className={cx('techtyl-tools-results', view === 'cards' && 'techtyl-tools-results-cards', page > 0 && 'techtyl-tools-static')}>
                                {hits.map((hit) => (
                                    <article key={hit.project_id} className={'techtyl-tools-item'}>
                                        <ProjectIcon url={hit.icon_url} title={hit.title} />
                                        <div className={'techtyl-tools-item-main'}>
                                            <div className={'techtyl-tools-item-title'}>
                                                <h3>
                                                    <button type={'button'} onClick={() => setDetail(hit.project_id)} title={t('open_project')}>
                                                        {hit.title}
                                                    </button>
                                                </h3>
                                                {hit.author && <span>{t('by_author', { author: hit.author })}</span>}
                                            </div>
                                            <p className={'techtyl-tools-item-text'}>{hit.description}</p>
                                            <div className={'techtyl-tools-item-meta'}>
                                                <span>
                                                    <Icon id={'download'} size={14} /> {formatCount(hit.downloads)}
                                                </span>
                                                <span>
                                                    <Icon id={'heart'} size={14} /> {formatCount(hit.follows)}
                                                </span>
                                                {hit.updated_at && (
                                                    <span>
                                                        <Icon id={'clock'} size={14} /> {formatDate(hit.updated_at)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className={'techtyl-tools-item-side'}>{sideFor(hit)}</div>
                                    </article>
                                ))}
                            </div>
                            {page + 1 < pages && (
                                <div className={'techtyl-tools-more'}>
                                    <Button busy={searching} onClick={() => runSearch(page + 1)}>
                                        {t('load_more')}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}

            {tab === 'installed' && (
                <div className={'techtyl-tools-panel'}>
                    <div className={'techtyl-tools-panel-title'}>
                        <h2>{t('in_directory', { directory: tool.directory || '' })}</h2>
                        <div className={'techtyl-tools-chips'}>
                            {checking && <Spinner label={t('checking_updates')} />}
                            {updateCount > 1 && can.install && can.remove && (
                                <Button
                                    small
                                    look={'primary'}
                                    onClick={() => installed && installed.filter((item) => updates && updates.updates[item.filename] && item.project_id && !taskByProject[item.project_id]).forEach(runUpdate)}
                                >
                                    <Icon id={'up'} /> {t('update_all', { n: updateCount })}
                                </Button>
                            )}
                            <Button small onClick={() => loadInstalled(true)} disabled={checking}>
                                <Icon id={'refresh'} /> {t('recheck')}
                            </Button>
                        </div>
                    </div>

                    {updates && !updates.checked && updates.reason && <Notice tone={'info'}>{updates.reason}</Notice>}

                    {installedError ? (
                        <Empty icon={'warn'} title={t('folder_unreadable')} action={<Button onClick={() => loadInstalled(true)}>{t('retry')}</Button>}>
                            {installedError}
                        </Empty>
                    ) : installed === null ? (
                        <SkeletonRows rows={4} />
                    ) : installed.length === 0 ? (
                        <Empty title={t(isMod ? 'empty_mods' : 'empty_plugins')} action={<Button look={'primary'} onClick={() => setTab('browse')}>{t(isMod ? 'discover_mods' : 'discover_plugins')}</Button>}>
                            {t('empty_text', { directory: tool.directory || '' })}
                        </Empty>
                    ) : (
                        <div className={'techtyl-tools-results techtyl-tools-static'}>
                            {installed.map((item) => {
                                const update = updates ? updates.updates[item.filename] : undefined;
                                const task = item.project_id ? taskByProject[item.project_id] : undefined;

                                return (
                                    <article key={item.filename} className={'techtyl-tools-item'}>
                                        <ProjectIcon url={item.icon_url} title={item.title || item.filename} />
                                        <div className={'techtyl-tools-item-main'}>
                                            <div className={'techtyl-tools-item-title'}>
                                                <h3>
                                                    {item.project_id ? (
                                                        <button type={'button'} onClick={() => setDetail(item.project_id)}>
                                                            {item.title}
                                                        </button>
                                                    ) : (
                                                        item.filename
                                                    )}
                                                </h3>
                                                {item.version_number && <span>{item.version_number}</span>}
                                                {update && (
                                                    <span className={'techtyl-tools-tag techtyl-tools-tag-warn'}>
                                                        <Icon id={'up'} size={12} /> {t('update_available', { version: update.version_number })}
                                                    </span>
                                                )}
                                                {item.state === 'unknown' && (
                                                    <span className={'techtyl-tools-tag'} title={t('unknown_title')}>
                                                        {t('unknown')}
                                                    </span>
                                                )}
                                                {item.state === 'changed' && (
                                                    <span className={'techtyl-tools-tag techtyl-tools-tag-warn'} title={t('changed_title')}>
                                                        {t('changed')}
                                                    </span>
                                                )}
                                            </div>
                                            <div className={'techtyl-tools-item-meta'}>
                                                {item.project_id && <span className={'techtyl-tools-mono'}>{item.filename}</span>}
                                                <span>{formatBytes(item.size)}</span>
                                                {item.state === 'unknown' && <span>{t('uploaded_by_hand')}</span>}
                                                {item.installed_at && <span>{t('installed_when', { when: formatDate(item.installed_at) })}</span>}
                                            </div>
                                        </div>
                                        <div className={'techtyl-tools-item-side'}>
                                            {task ? (
                                                <>
                                                    <Bar value={task.status === 'pending' ? 2 : task.progress} label={t('updating_label', { title: item.title || item.filename })} />
                                                    <span className={'techtyl-tools-item-progress'}>{t('step_progress', { step: task.step_label || t('waiting'), n: task.progress })}</span>
                                                </>
                                            ) : (
                                                <>
                                                    {update && can.install && can.remove && (
                                                        <Button small look={'primary'} onClick={() => runUpdate(item)}>
                                                            <Icon id={'up'} /> {t('update')}
                                                        </Button>
                                                    )}
                                                    {can.remove && (
                                                        <Button small look={'danger'} onClick={() => setRemoving(item)}>
                                                            <Icon id={'trash'} /> {t('remove')}
                                                        </Button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {detail && (
                <ProjectDialog
                    server={server}
                    projectId={detail}
                    kindLabel={singular}
                    canInstall={!!can.install}
                    task={taskByProject[detail]}
                    onClose={() => setDetail(null)}
                    onInstall={beginInstall}
                />
            )}

            {prompt && <DependencyDialog prompt={prompt} kindWord={plural} onClose={() => setPrompt(null)} onConfirm={confirmDependencies} />}

            {removing && (
                <Dialog
                    title={t('remove_title', { title: removing.title || removing.filename })}
                    onClose={() => !removeBusy && setRemoving(null)}
                    footer={
                        <>
                            <Button onClick={() => setRemoving(null)} disabled={removeBusy}>
                                {t('cancel')}
                            </Button>
                            <Button look={'danger'} busy={removeBusy} onClick={confirmRemove}>
                                <Icon id={'trash'} /> {t('remove')}
                            </Button>
                        </>
                    }
                >
                    <p>{t('remove_text', { file: removing.filename, directory: tool.directory || '' })}</p>
                    {status && status !== 'offline' && (
                        <p className={'techtyl-tools-muted'} style={{ marginTop: 8 }}>
                            {t('remove_running')}
                        </p>
                    )}
                </Dialog>
            )}

            <Toasts toasts={toasts} dismiss={dismiss} />
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_mods')}>
            <ToolsGate tool={'content'} unavailable={(meta) => (meta.type === 'vanilla' ? { title: t('vanilla_title'), text: t('vanilla_text') } : null)}>
                {(meta, server) => <ContentPage meta={meta} server={server} />}
            </ToolsGate>
        </ServerContentBlock>
    );
};
