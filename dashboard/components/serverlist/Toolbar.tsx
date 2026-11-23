import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowDown,
    faArrowUp,
    faBars,
    faList,
    faPen,
    faPlay,
    faPlus,
    faPowerOff,
    faRedo,
    faSearch,
    faTh,
    faThLarge,
    faTimes,
    faTrash,
} from '@fortawesome/free-solid-svg-icons';
import texts from '@blueprint/extensions/techtyl/i18n/serverlist';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import {
    createFolder,
    errorText,
    fetchFacts,
    folderIssue,
    loadBootstrapBits,
    orderFolders,
    readAdminList,
    readPage,
    removeFolder,
    renameFolder,
    runPower,
    saveLayout,
} from './api';
import { applyListFilter, emptySnapshot, ListFilter, ListSnapshot, percentOf, watchServerList } from './dom';
import Dialog from './Dialog';
import { readChip, readFolder, readQuery, writeChip, writeFolder, writeQuery } from './storage';
import {
    clearSelected,
    deselectIds,
    getListState,
    hasId,
    pruneSelected,
    selectIds,
    setFacts,
    setFolders,
    setSelecting,
    useListState,
} from './store';
import { Chip, Folder, FolderPick, isLayout, LayoutId, ListedServer, MAX_FOLDERS, NameIssue, PowerReport, Signal } from './types';
import './styles.css';

const CHIPS: Chip[] = ['all', 'running', 'offline', 'mine', 'shared'];

type Pending = '' | 'create' | 'rename' | 'delete' | 'order' | 'layout';

interface Notice {
    tone: 'ok' | 'bad';
    text: string;
}

const folderIndexOf = (folders: Folder[], id: number): number => {
    for (let i = 0; i < folders.length; i++) {
        if (folders[i].id === id) return i;
    }
    return -1;
};

const snapKey = (value: ListSnapshot): string => {
    let out = String(value.total) + '|' + String(value.shown) + '|' + String(value.loose) + '|' + (value.paged ? '1' : '0');
    for (let i = 0; i < value.cards.length; i++) {
        const card = value.cards[i];
        out += '\n' + card.id + '\t' + (card.hidden ? '0' : '1') + '\t' + card.label;
    }
    return out;
};

const readLayout = (): LayoutId => {
    if (typeof document === 'undefined') return 'raster';
    const current = document.documentElement.getAttribute('data-techtyl-servers');
    return isLayout(current) ? current : 'raster';
};

// The panel draws the cards. This bar only filters the ones on the current page.
export default () => {
    const t = useT(texts);
    const list = useListState();
    const rootRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const draftRef = useRef<HTMLInputElement>(null);
    const renameRef = useRef<HTMLInputElement>(null);
    const tabRefs = useRef<{ [key: string]: HTMLButtonElement | null }>({});
    const dragId = useRef<number | null>(null);
    const mounted = useRef(true);
    const pendingRef = useRef<Pending>('');
    const cardKeyRef = useRef('');
    const publishRef = useRef<() => void>(() => undefined);
    const filterRef = useRef<ListFilter>({
        query: '',
        chip: 'all',
        folder: 'all',
        membership: {},
        ownerReady: false,
    });

    const [query, setQuery] = useState(readQuery);
    const [chip, setChip] = useState<Chip>(readChip);
    const [folder, setFolder] = useState<FolderPick>(readFolder);
    const [layout, setLayout] = useState<LayoutId>(readLayout);
    const [canLayout, setCanLayout] = useState<boolean | null>(null);
    const [snap, setSnap] = useState<ListSnapshot>(emptySnapshot);
    const [cardKey, setCardKey] = useState('');
    const [bootTick, setBootTick] = useState(0);
    const [bootError, setBootError] = useState(false);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [pending, setPending] = useState<Pending>('');
    const [creating, setCreating] = useState(false);
    const [draft, setDraft] = useState('');
    const [draftIssue, setDraftIssue] = useState<NameIssue | null>(null);
    const [renaming, setRenaming] = useState(false);
    const [renameDraft, setRenameDraft] = useState('');
    const [renameIssue, setRenameIssue] = useState<NameIssue | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [dialogError, setDialogError] = useState('');
    const [dragOver, setDragOver] = useState<number | null>(null);
    const [powerConfirm, setPowerConfirm] = useState<{ signal: Signal; items: ListedServer[] } | null>(null);
    const [powerBusy, setPowerBusy] = useState(false);
    const [powerProgress, setPowerProgress] = useState<{ done: number; total: number } | null>(null);
    const [powerReport, setPowerReport] = useState<PowerReport | null>(null);

    const membership = useMemo(() => {
        const map: { [id: string]: number } = {};
        for (let i = 0; i < list.folders.length; i++) {
            const entry = list.folders[i];
            for (let s = 0; s < entry.servers.length; s++) {
                map[entry.servers[s].toLowerCase()] = entry.id;
            }
        }
        return map;
    }, [list.folders]);

    const trimmed = query.trim();
    // A saved folder must not hide every card while the folder list is still loading.
    let folderForFilter: FolderPick = folder;
    if (!list.foldersReady || (typeof folder === 'number' && folderIndexOf(list.folders, folder) === -1)) {
        folderForFilter = 'all';
    }
    const filter = useMemo<ListFilter>(
        () => ({
            query: trimmed,
            chip: chip,
            folder: folderForFilter,
            membership: membership,
            ownerReady: list.factsReady,
        }),
        [trimmed, chip, folderForFilter, membership, list.factsReady]
    );
    filterRef.current = filter;

    const publish = () => {
        const root = rootRef.current;
        const parent = root ? root.parentElement : null;
        if (!parent) return;
        const next = applyListFilter(parent, filterRef.current);
        const nextKey = next.ids.join('|');
        setSnap((prev) => (snapKey(prev) === snapKey(next) ? prev : next));
        if (cardKeyRef.current !== nextKey) {
            cardKeyRef.current = nextKey;
            setCardKey(nextKey);
            pruneSelected(next.ids);
        }
    };
    publishRef.current = publish;

    const begin = (kind: Pending): boolean => {
        if (pendingRef.current !== '') return false;
        pendingRef.current = kind;
        setPending(kind);
        return true;
    };

    const finish = () => {
        pendingRef.current = '';
        if (mounted.current) setPending('');
    };

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
        };
    }, []);

    useEffect(() => {
        let active = true;
        setBootError(false);
        loadBootstrapBits()
            .then((bits) => {
                if (!active) return;
                setFolders(bits.folders, true);
                setCanLayout(bits.canLayout);
                if (bits.layout) {
                    setLayout(bits.layout);
                    document.documentElement.setAttribute('data-techtyl-servers', bits.layout);
                }
            })
            .catch(() => {
                if (!active) return;
                setFolders([], true);
                setCanLayout(false);
                setBootError(true);
            });
        return () => {
            active = false;
        };
    }, [bootTick]);

    useEffect(() => {
        if (!list.foldersReady || typeof folder !== 'number') return;
        if (folderIndexOf(list.folders, folder) === -1) setFolder('all');
    }, [list.folders, list.foldersReady, folder]);

    useEffect(() => {
        writeQuery(query);
    }, [query]);

    useEffect(() => {
        writeChip(chip);
    }, [chip]);

    useEffect(() => {
        writeFolder(folder);
    }, [folder]);

    useEffect(() => {
        setRenaming(false);
        setRenameDraft('');
        setRenameIssue(null);
    }, [folder]);

    useEffect(() => {
        if (creating && draftRef.current) draftRef.current.focus();
    }, [creating]);

    useEffect(() => {
        if (renaming && renameRef.current) renameRef.current.focus();
    }, [renaming]);

    useEffect(() => {
        const root = rootRef.current;
        const parent = root ? root.parentElement : null;
        if (!parent) return undefined;
        publishRef.current();
        return watchServerList(parent, () => {
            publishRef.current();
        });
    }, []);

    useEffect(() => {
        publishRef.current();
    }, [filter]);

    useEffect(() => {
        if (cardKey === '') return undefined;
        let active = true;
        setFacts(getListState().facts, false, false);
        const ids = cardKey.split('|');
        const page = readPage();
        const admin = readAdminList();
        const absent = (facts: { [id: string]: { owner: boolean; egg: string } }): boolean => {
            for (let i = 0; i < ids.length; i++) {
                if (!facts[ids[i].toLowerCase()]) return true;
            }
            return false;
        };
        fetchFacts(page, admin)
            .then((first) => {
                if (!active) return null;
                if (!absent(first)) return first;
                return fetchFacts(page, !admin).then(
                    (second) => ({ ...first, ...second }),
                    () => first
                );
            })
            .then((facts) => {
                if (!active || !facts) return;
                setFacts(facts, true, absent(facts));
            })
            .catch(() => {
                if (!active) return;
                setFacts({}, true, true);
            });
        return () => {
            active = false;
        };
    }, [cardKey]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.repeat || event.key !== '/' || event.altKey) return;
            if (!event.ctrlKey && !event.metaKey) return;
            if (document.getElementById('techtyl-toolbar-dialog-title')) return;
            const input = searchRef.current;
            if (!input) return;
            event.preventDefault();
            input.focus();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const picks: FolderPick[] = ['all', 'none'];
    for (let i = 0; i < list.folders.length; i++) picks.push(list.folders[i].id);

    let activeFolder: Folder | null = null;
    let activeIndex = -1;
    if (typeof folder === 'number') {
        activeIndex = folderIndexOf(list.folders, folder);
        if (activeIndex >= 0) activeFolder = list.folders[activeIndex];
    }

    let deleteTarget: Folder | null = null;
    if (deleteId !== null) {
        const index = folderIndexOf(list.folders, deleteId);
        if (index >= 0) deleteTarget = list.folders[index];
    }

    const folderBusy = pending === 'create' || pending === 'rename' || pending === 'delete' || pending === 'order';

    const moveFolder = (from: number, toIndex: number) => {
        const previous = getListState().folders.slice();
        if (from < 0 || toIndex < 0 || from >= previous.length || toIndex >= previous.length || from === toIndex) return;
        if (!begin('order')) return;
        const next = previous.slice();
        const item = next[from];
        next.splice(from, 1);
        next.splice(toIndex, 0, item);
        setFolders(next, true);
        const ids: number[] = [];
        for (let i = 0; i < next.length; i++) ids.push(next[i].id);
        orderFolders(ids)
            .then((folders) => {
                if (!mounted.current) return;
                setFolders(folders, true);
            })
            .catch((error: unknown) => {
                if (!mounted.current) return;
                setFolders(previous, true);
                setNotice({ tone: 'bad', text: errorText(error, t('error.generic')) });
            })
            .then(finish);
    };

    const chooseTab = (pick: FolderPick) => {
        setFolder(pick);
        const node = tabRefs.current[String(pick)];
        if (node) node.focus();
    };

    const onTabKey = (event: React.KeyboardEvent<HTMLDivElement>) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.getAttribute('role') !== 'tab') return;
        let index = -1;
        const mark = target.getAttribute('data-pick') || '';
        for (let i = 0; i < picks.length; i++) {
            if (String(picks[i]) === mark) index = i;
        }
        if (index < 0) return;
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft' || event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % picks.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + picks.length) % picks.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = picks.length - 1;
            chooseTab(picks[next]);
            return;
        }
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
        const pick = picks[index];
        if (typeof pick !== 'number') return;
        const from = folderIndexOf(list.folders, pick);
        if (from < 0) return;
        const to = event.key === 'ArrowUp' ? from - 1 : from + 1;
        if (to < 0 || to >= list.folders.length) return;
        event.preventDefault();
        moveFolder(from, to);
    };

    const submitCreate = () => {
        const issue = folderIssue(draft);
        setDraftIssue(issue);
        if (issue || list.folders.length >= MAX_FOLDERS) {
            if (!issue && list.folders.length >= MAX_FOLDERS) {
                setNotice({ tone: 'bad', text: t('folder.limit', { max: MAX_FOLDERS }) });
            }
            return;
        }
        if (!begin('create')) return;
        createFolder(draft)
            .then((created) => {
                if (!mounted.current) return;
                const current = getListState().folders.filter((item) => item.id !== created.id);
                current.push(created);
                current.sort((left, right) => left.position - right.position || left.id - right.id);
                setFolders(current, true);
                setCreating(false);
                setDraft('');
                setDraftIssue(null);
                setFolder(created.id);
            })
            .catch((error: unknown) => {
                if (!mounted.current) return;
                setNotice({ tone: 'bad', text: errorText(error, t('error.generic')) });
            })
            .then(finish);
    };

    const submitRename = () => {
        if (!activeFolder) return;
        const issue = folderIssue(renameDraft);
        setRenameIssue(issue);
        if (issue) return;
        const id = activeFolder.id;
        if (!begin('rename')) return;
        renameFolder(id, renameDraft)
            .then((updated) => {
                if (!mounted.current) return;
                const current = getListState().folders.map((item) => (item.id === updated.id ? updated : item));
                setFolders(current, true);
                setRenaming(false);
                setRenameIssue(null);
            })
            .catch((error: unknown) => {
                if (!mounted.current) return;
                setNotice({ tone: 'bad', text: errorText(error, t('error.generic')) });
            })
            .then(finish);
    };

    const commitDelete = () => {
        if (deleteId === null) return;
        const id = deleteId;
        if (!begin('delete')) return;
        removeFolder(id)
            .then(() => {
                if (!mounted.current) return;
                setFolders(
                    getListState().folders.filter((item) => item.id !== id),
                    true
                );
                if (folder === id) setFolder('all');
                setDeleteId(null);
                setDialogError('');
            })
            .catch((error: unknown) => {
                if (!mounted.current) return;
                setDialogError(errorText(error, t('error.generic')));
            })
            .then(finish);
    };

    const pickLayout = (next: LayoutId) => {
        if (next === layout || !begin('layout')) return;
        const previous = layout;
        setLayout(next);
        document.documentElement.setAttribute('data-techtyl-servers', next);
        saveLayout(next)
            .then(() => {
                if (!mounted.current) return;
                setNotice({ tone: 'ok', text: t('layout.saved') });
            })
            .catch(() => {
                if (!mounted.current) return;
                setLayout(previous);
                document.documentElement.setAttribute('data-techtyl-servers', previous);
                setNotice({ tone: 'bad', text: t('layout.failed') });
            })
            .then(finish);
    };

    const selectedItems = (): ListedServer[] => {
        const labels: { [key: string]: string } = {};
        for (let i = 0; i < snap.cards.length; i++) {
            labels[snap.cards[i].id.toLowerCase()] = snap.cards[i].label;
        }
        const items: ListedServer[] = [];
        for (let i = 0; i < list.selected.length; i++) {
            const id = list.selected[i];
            const label = labels[id.toLowerCase()];
            if (!label) continue;
            items.push({ id: id, label: label });
        }
        return items;
    };

    const startPower = (signal: Signal, items: ListedServer[]) => {
        setPowerConfirm(null);
        setPowerBusy(true);
        setPowerProgress({ done: 0, total: items.length });
        setPowerReport(null);
        runPower(items, signal, (done, total) => {
            if (mounted.current) setPowerProgress({ done: done, total: total });
        })
            .then((result) => {
                if (!mounted.current) return;
                const failed: { id: string; label: string; detail: string }[] = [];
                for (let i = 0; i < result.failed.length; i++) {
                    const item = result.failed[i];
                    failed.push({ id: item.id, label: item.label, detail: item.detail || t('bulk.failed') });
                }
                setPowerReport({ signal: signal, ok: result.ok, failed: failed });
            })
            .catch((error: unknown) => {
                if (!mounted.current) return;
                setNotice({ tone: 'bad', text: errorText(error, t('error.generic')) });
            })
            .then(() => {
                if (!mounted.current) return;
                setPowerBusy(false);
                setPowerProgress(null);
            });
    };

    const askPower = (signal: Signal) => {
        if (powerBusy) return;
        const items = selectedItems();
        if (items.length === 0) return;
        if ((signal === 'stop' || signal === 'restart') && items.length > 1) {
            setPowerConfirm({ signal: signal, items: items });
            return;
        }
        startPower(signal, items);
    };

    let visibleChosen = 0;
    for (let i = 0; i < snap.visibleIds.length; i++) {
        if (hasId(list.selected, snap.visibleIds[i])) visibleChosen += 1;
    }
    const allVisible = snap.visibleIds.length > 0 && visibleChosen === snap.visibleIds.length;

    const empty = (): { title: string; hint: string } | null => {
        if (snap.shown > 0 || snap.total === 0) return null;
        if (trimmed !== '') {
            return { title: t('empty.search', { query: trimmed }), hint: snap.paged ? t('empty.searchNext') : '' };
        }
        if (typeof folder === 'number') {
            return { title: t('empty.folder'), hint: t('empty.folderHint') };
        }
        if (folder === 'none') return { title: t('empty.none'), hint: '' };
        if ((chip === 'mine' || chip === 'shared') && list.factsError) {
            return { title: t('empty.owner'), hint: '' };
        }
        if (chip === 'running') return { title: t('empty.running'), hint: '' };
        if (chip === 'offline') return { title: t('empty.offline'), hint: '' };
        if (chip === 'mine') return { title: t('empty.mine'), hint: '' };
        if (chip === 'shared') return { title: t('empty.shared'), hint: '' };
        return { title: t('empty.filters'), hint: '' };
    };
    const blank = empty();
    const showBar = list.selecting || powerBusy || powerReport !== null;

    const onSearchKey = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
        event.nativeEvent.stopImmediatePropagation();
        if (query !== '') setQuery('');
        else event.currentTarget.blur();
    };

    const rememberTab = (key: string) => (node: HTMLButtonElement | null) => {
        tabRefs.current[key] = node;
    };

    const layoutChoices: { id: LayoutId; label: string; icon: typeof faThLarge }[] = [
        { id: 'raster', label: t('layout.grid'), icon: faThLarge },
        { id: 'liste', label: t('layout.list'), icon: faList },
        { id: 'kompakt', label: t('layout.compact'), icon: faTh },
    ];

    const signalChoices: { id: Signal; label: string; icon: typeof faPlay }[] = [
        { id: 'start', label: t('bulk.start'), icon: faPlay },
        { id: 'restart', label: t('bulk.restart'), icon: faRedo },
        { id: 'stop', label: t('bulk.stop'), icon: faPowerOff },
    ];

    const tabButton = (pick: FolderPick, label: string, count: number) => (
        <button
            type={'button'}
            role={'tab'}
            className={'techtyl-toolbar-tab'}
            data-pick={String(pick)}
            aria-selected={folder === pick}
            aria-label={t('folder.tab', { label: label, count: count })}
            tabIndex={folder === pick ? 0 : -1}
            ref={rememberTab(String(pick))}
            onClick={() => setFolder(pick)}
        >
            <span className={'techtyl-toolbar-tab-label'}>{label}</span>
            <span className={'techtyl-toolbar-badge'}>{count}</span>
        </button>
    );

    const bar = (
        <div className={'techtyl-toolbar-bulk'} role={'region'} aria-label={t('bulk.region')} aria-busy={powerBusy}>
            <p className={'techtyl-toolbar-bulk-count'}>
                {powerReport
                    ? powerReport.failed.length === 0
                        ? t('bulk.allOk', { ok: powerReport.ok })
                        : t('bulk.summary', { ok: powerReport.ok, failed: powerReport.failed.length })
                    : list.selected.length === 0
                      ? t('bulk.hint')
                      : t('bulk.count', { count: list.selected.length })}
            </p>
            {powerProgress && (
                <p className={'techtyl-toolbar-meta'} role={'status'}>
                    {t('bulk.progress', {
                        done: powerProgress.done,
                        total: powerProgress.total,
                        percent: percentOf(powerProgress.done, powerProgress.total),
                    })}
                </p>
            )}
            <div className={'techtyl-toolbar-bulk-actions'}>
                {signalChoices.map((choice) => (
                    <button
                        key={choice.id}
                        type={'button'}
                        className={'techtyl-toolbar-btn' + (choice.id === 'stop' ? ' techtyl-toolbar-danger' : '')}
                        disabled={powerBusy || list.selected.length === 0}
                        onClick={() => askPower(choice.id)}
                    >
                        <FontAwesomeIcon icon={choice.icon} />
                        <span>{choice.label}</span>
                    </button>
                ))}
                <button
                    type={'button'}
                    className={'techtyl-toolbar-btn'}
                    disabled={powerBusy || list.selected.length === 0}
                    onClick={() => clearSelected()}
                >
                    {t('bulk.clear')}
                </button>
            </div>
            {powerReport && powerReport.failed.length > 0 && (
                <div className={'techtyl-toolbar-results'} role={'status'}>
                    <ul className={'techtyl-toolbar-names'}>
                        {powerReport.failed.map((item) => (
                            <li key={item.id}>
                                <b>{item.label}</b>
                                <span>{item.detail || t('bulk.failed')}</span>
                            </li>
                        ))}
                    </ul>
                    <button type={'button'} className={'techtyl-toolbar-btn'} onClick={() => setPowerReport(null)}>
                        {t('bulk.dismiss')}
                    </button>
                </div>
            )}
            {powerReport && powerReport.failed.length === 0 && (
                <button type={'button'} className={'techtyl-toolbar-btn'} onClick={() => setPowerReport(null)}>
                    {t('bulk.dismiss')}
                </button>
            )}
        </div>
    );

    return (
        <div className={'techtyl-toolbar'} ref={rootRef} data-bulk={showBar ? '1' : undefined}>
            <form
                className={'techtyl-toolbar-search'}
                role={'search'}
                onSubmit={(event) => {
                    event.preventDefault();
                }}
            >
                <label className={'techtyl-toolbar-sr'} htmlFor={'techtyl-server-search'}>
                    {t('search.label')}
                </label>
                <FontAwesomeIcon icon={faSearch} />
                <input
                    id={'techtyl-server-search'}
                    ref={searchRef}
                    type={'search'}
                    value={query}
                    maxLength={200}
                    autoComplete={'off'}
                    spellCheck={false}
                    placeholder={t('search.placeholder')}
                    onChange={(event) => setQuery(event.target.value.slice(0, 200))}
                    onKeyDown={onSearchKey}
                />
                <span className={'techtyl-toolbar-kbd'}>{t('search.hint')}</span>
                {query !== '' && (
                    <button
                        type={'button'}
                        className={'techtyl-toolbar-icon'}
                        aria-label={t('search.clear')}
                        onClick={() => {
                            setQuery('');
                            if (searchRef.current) searchRef.current.focus();
                        }}
                    >
                        <FontAwesomeIcon icon={faTimes} />
                    </button>
                )}
            </form>

            {list.factsError && (chip === 'mine' || chip === 'shared') && (
                <p className={'techtyl-toolbar-notice is-bad'} role={'alert'}>
                    {t('empty.owner')}
                </p>
            )}

            {notice && (
                <p className={'techtyl-toolbar-notice' + (notice.tone === 'bad' ? ' is-bad' : ' is-ok')} role={notice.tone === 'bad' ? 'alert' : 'status'}>
                    {notice.text}
                </p>
            )}

            <div className={'techtyl-toolbar-row'}>
                <div className={'techtyl-toolbar-chips'} role={'group'} aria-label={t('filters.label')}>
                    {CHIPS.map((item) => (
                        <button
                            key={item}
                            type={'button'}
                            className={'techtyl-toolbar-chip' + (chip === item ? ' is-on' : '')}
                            aria-pressed={chip === item}
                            onClick={() => setChip(item)}
                        >
                            {t('chip.' + item)}
                        </button>
                    ))}
                </div>
                <span className={'techtyl-toolbar-grow'} />
                <button
                    type={'button'}
                    className={'techtyl-toolbar-btn' + (list.selecting ? ' is-on' : '')}
                    aria-pressed={list.selecting}
                    onClick={() => setSelecting(!list.selecting)}
                >
                    {list.selecting ? t('select.done') : t('select.mode')}
                </button>
                {list.selecting && (
                    <button
                        type={'button'}
                        className={'techtyl-toolbar-btn'}
                        disabled={snap.visibleIds.length === 0}
                        onClick={() => (allVisible ? deselectIds(snap.visibleIds) : selectIds(snap.visibleIds))}
                    >
                        {allVisible ? t('select.none') : t('select.all')}
                    </button>
                )}
                {canLayout === true && (
                    <div className={'techtyl-toolbar-segment'} role={'group'} aria-label={t('layout.label')}>
                        {layoutChoices.map((choice) => (
                            <button
                                key={choice.id}
                                type={'button'}
                                className={layout === choice.id ? 'is-on' : undefined}
                                aria-pressed={layout === choice.id}
                                disabled={pending === 'layout'}
                                onClick={() => pickLayout(choice.id)}
                            >
                                <FontAwesomeIcon icon={choice.icon} />
                                <span>{choice.label}</span>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div className={'techtyl-toolbar-folders'}>
                {!list.foldersReady && !bootError && <p className={'techtyl-toolbar-hint'} role={'status'}>{t('folder.loading')}</p>}
                {bootError && (
                    <div className={'techtyl-toolbar-notice is-bad'} role={'alert'}>
                        <span>{t('folder.failed')}</span>{' '}
                        <button type={'button'} className={'techtyl-toolbar-btn'} onClick={() => setBootTick((value) => value + 1)}>
                            {t('folder.retry')}
                        </button>
                    </div>
                )}
                <div className={'techtyl-toolbar-tabs'} role={'tablist'} aria-label={t('folder.label')} onKeyDown={onTabKey}>
                    {tabButton('all', t('folder.all'), snap.total)}
                    {tabButton('none', t('folder.none'), snap.loose)}
                    {list.folders.map((entry) => (
                        <div
                            key={entry.id}
                            className={'techtyl-toolbar-folder' + (dragOver === entry.id ? ' is-drop' : '')}
                            onDragOver={(event) => {
                                if (dragId.current === null) return;
                                event.preventDefault();
                                event.dataTransfer.dropEffect = 'move';
                                if (dragOver !== entry.id) setDragOver(entry.id);
                            }}
                            onDrop={(event) => {
                                event.preventDefault();
                                const fromId = dragId.current;
                                dragId.current = null;
                                setDragOver(null);
                                if (fromId === null) return;
                                const foldersNow = getListState().folders;
                                moveFolder(folderIndexOf(foldersNow, fromId), folderIndexOf(foldersNow, entry.id));
                            }}
                        >
                            <button
                                type={'button'}
                                className={'techtyl-toolbar-grip'}
                                draggable={!folderBusy}
                                tabIndex={-1}
                                aria-label={t('folder.drag', { label: entry.name })}
                                onDragStart={(event) => {
                                    dragId.current = entry.id;
                                    event.dataTransfer.setData('text/plain', String(entry.id));
                                    event.dataTransfer.effectAllowed = 'move';
                                }}
                                onDragEnd={() => {
                                    dragId.current = null;
                                    setDragOver(null);
                                }}
                            >
                                <FontAwesomeIcon icon={faBars} />
                            </button>
                            <button
                                type={'button'}
                                role={'tab'}
                                className={'techtyl-toolbar-tab'}
                                data-pick={String(entry.id)}
                                aria-selected={folder === entry.id}
                                tabIndex={folder === entry.id ? 0 : -1}
                                aria-label={t('folder.tab', { label: entry.name, count: entry.servers.length })}
                                ref={rememberTab(String(entry.id))}
                                onClick={() => setFolder(entry.id)}
                            >
                                <span className={'techtyl-toolbar-tab-label'}>{entry.name}</span>
                                <span className={'techtyl-toolbar-badge'}>{entry.servers.length}</span>
                            </button>
                        </div>
                    ))}
                </div>
                <p className={'techtyl-toolbar-sr'}>{t('folder.keys')}</p>
                <div className={'techtyl-toolbar-actions'}>
                    <button
                        type={'button'}
                        className={'techtyl-toolbar-btn'}
                        disabled={folderBusy || list.folders.length >= MAX_FOLDERS}
                        onClick={() => {
                            setCreating(true);
                            setRenaming(false);
                            setDraft('');
                            setDraftIssue(null);
                        }}
                    >
                        <FontAwesomeIcon icon={faPlus} />
                        <span>{t('folder.new')}</span>
                    </button>
                    {list.folders.length >= MAX_FOLDERS && (
                        <p className={'techtyl-toolbar-hint'}>{t('folder.limit', { max: MAX_FOLDERS })}</p>
                    )}
                    {activeFolder && (
                        <>
                            <button
                                type={'button'}
                                className={'techtyl-toolbar-btn techtyl-toolbar-square'}
                                aria-label={t('folder.up')}
                                disabled={folderBusy || activeIndex <= 0}
                                onClick={() => moveFolder(activeIndex, activeIndex - 1)}
                            >
                                <FontAwesomeIcon icon={faArrowUp} />
                            </button>
                            <button
                                type={'button'}
                                className={'techtyl-toolbar-btn techtyl-toolbar-square'}
                                aria-label={t('folder.down')}
                                disabled={folderBusy || activeIndex < 0 || activeIndex >= list.folders.length - 1}
                                onClick={() => moveFolder(activeIndex, activeIndex + 1)}
                            >
                                <FontAwesomeIcon icon={faArrowDown} />
                            </button>
                            <button
                                type={'button'}
                                className={'techtyl-toolbar-btn'}
                                disabled={folderBusy}
                                onClick={() => {
                                    if (!activeFolder) return;
                                    setRenameDraft(activeFolder.name);
                                    setRenameIssue(null);
                                    setRenaming(true);
                                    setCreating(false);
                                }}
                            >
                                <FontAwesomeIcon icon={faPen} />
                                <span>{t('folder.rename')}</span>
                            </button>
                            <button
                                type={'button'}
                                className={'techtyl-toolbar-btn techtyl-toolbar-danger'}
                                disabled={folderBusy}
                                onClick={() => {
                                    if (!activeFolder) return;
                                    setDialogError('');
                                    setDeleteId(activeFolder.id);
                                }}
                            >
                                <FontAwesomeIcon icon={faTrash} />
                                <span>{t('folder.delete')}</span>
                            </button>
                        </>
                    )}
                </div>
                {creating && (
                    <form
                        className={'techtyl-toolbar-form'}
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitCreate();
                        }}
                    >
                        <label className={'techtyl-toolbar-sr'} htmlFor={'techtyl-folder-draft'}>
                            {t('folder.new')}
                        </label>
                        <input
                            id={'techtyl-folder-draft'}
                            className={'techtyl-toolbar-field'}
                            ref={draftRef}
                            value={draft}
                            maxLength={40}
                            autoComplete={'off'}
                            aria-invalid={draftIssue ? true : undefined}
                            aria-describedby={draftIssue ? 'techtyl-folder-draft-error' : undefined}
                            onChange={(event) => {
                                setDraft(event.target.value);
                                if (draftIssue) setDraftIssue(null);
                            }}
                            onKeyDown={(event) => {
                                if (event.key !== 'Escape') return;
                                event.preventDefault();
                                event.stopPropagation();
                                event.nativeEvent.stopImmediatePropagation();
                                setCreating(false);
                                setDraft('');
                                setDraftIssue(null);
                            }}
                        />
                        <button type={'submit'} className={'techtyl-toolbar-btn techtyl-toolbar-primary'} disabled={folderBusy}>
                            {t('folder.create')}
                        </button>
                        <button
                            type={'button'}
                            className={'techtyl-toolbar-btn'}
                            onClick={() => {
                                setCreating(false);
                                setDraft('');
                                setDraftIssue(null);
                            }}
                        >
                            {t('folder.cancel')}
                        </button>
                        {draftIssue && (
                            <p id={'techtyl-folder-draft-error'} className={'techtyl-toolbar-issue'} role={'alert'}>
                                {t('folder.err.' + draftIssue)}
                            </p>
                        )}
                    </form>
                )}
                {renaming && activeFolder && (
                    <form
                        className={'techtyl-toolbar-form'}
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitRename();
                        }}
                    >
                        <label className={'techtyl-toolbar-sr'} htmlFor={'techtyl-folder-rename'}>
                            {t('folder.rename')}
                        </label>
                        <input
                            id={'techtyl-folder-rename'}
                            className={'techtyl-toolbar-field'}
                            ref={renameRef}
                            value={renameDraft}
                            maxLength={40}
                            autoComplete={'off'}
                            aria-invalid={renameIssue ? true : undefined}
                            aria-describedby={renameIssue ? 'techtyl-folder-rename-error' : undefined}
                            onChange={(event) => {
                                setRenameDraft(event.target.value);
                                if (renameIssue) setRenameIssue(null);
                            }}
                            onKeyDown={(event) => {
                                if (event.key !== 'Escape') return;
                                event.preventDefault();
                                event.stopPropagation();
                                event.nativeEvent.stopImmediatePropagation();
                                setRenaming(false);
                                setRenameIssue(null);
                            }}
                        />
                        <button type={'submit'} className={'techtyl-toolbar-btn techtyl-toolbar-primary'} disabled={folderBusy}>
                            {t('folder.save')}
                        </button>
                        <button
                            type={'button'}
                            className={'techtyl-toolbar-btn'}
                            onClick={() => {
                                setRenaming(false);
                                setRenameIssue(null);
                            }}
                        >
                            {t('folder.cancel')}
                        </button>
                        {renameIssue && (
                            <p id={'techtyl-folder-rename-error'} className={'techtyl-toolbar-issue'} role={'alert'}>
                                {t('folder.err.' + renameIssue)}
                            </p>
                        )}
                    </form>
                )}
            </div>

            {snap.total > 0 && (
                <p className={'techtyl-toolbar-meta'} aria-live={'polite'}>
                    {t(snap.paged ? 'countPage' : 'count', { shown: snap.shown, total: snap.total })}
                </p>
            )}
            {blank && (
                <div className={'techtyl-toolbar-empty'} role={'status'}>
                    <p>{blank.title}</p>
                    {blank.hint !== '' && <p>{blank.hint}</p>}
                </div>
            )}

            {showBar && createPortal(bar, document.body)}

            {deleteTarget && (
                <Dialog
                    title={t('folder.deleteTitle', { label: deleteTarget.name })}
                    text={t('folder.deleteText')}
                    confirmLabel={t('folder.deleteConfirm')}
                    cancelLabel={t('folder.cancel')}
                    danger
                    busy={pending === 'delete'}
                    error={dialogError}
                    onConfirm={commitDelete}
                    onClose={() => {
                        if (pending === 'delete') return;
                        setDeleteId(null);
                        setDialogError('');
                    }}
                />
            )}
            {powerConfirm && (
                <Dialog
                    title={
                        powerConfirm.signal === 'stop'
                            ? t('bulk.confirmStop', { count: powerConfirm.items.length })
                            : t('bulk.confirmRestart', { count: powerConfirm.items.length })
                    }
                    text={t('bulk.confirmText')}
                    confirmLabel={powerConfirm.signal === 'stop' ? t('bulk.confirmStopAction') : t('bulk.confirmRestartAction')}
                    cancelLabel={t('folder.cancel')}
                    danger={powerConfirm.signal === 'stop'}
                    onConfirm={() => startPower(powerConfirm.signal, powerConfirm.items)}
                    onClose={() => setPowerConfirm(null)}
                >
                    <ul className={'techtyl-toolbar-names'}>
                        {powerConfirm.items.slice(0, 8).map((item) => (
                            <li key={item.id}>{item.label}</li>
                        ))}
                    </ul>
                    {powerConfirm.items.length > 8 && (
                        <p>{t('bulk.more', { count: powerConfirm.items.length - 8 })}</p>
                    )}
                </Dialog>
            )}
        </div>
    );
};
