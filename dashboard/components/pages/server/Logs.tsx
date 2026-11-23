import React, { useEffect, useMemo, useRef, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { CrashReport, explain, getCrash, getCrashes, getLog, LogView, shareLog, ToolsMeta } from '../../tools/api';
import { tt, useToolsT } from '../../tools/text';
import { Button, cx, Dialog, Empty, formatBytes, formatDate, Icon, Notice, SkeletonRows, Toasts, ToolsGate, useToasts } from '../../tools/ui';

type Level = 'INFO' | 'WARN' | 'ERROR';
type LevelFilter = 'all' | Level;

const LEVELS: LevelFilter[] = ['all', 'INFO', 'WARN', 'ERROR'];

const lineLevel = (line: string): Level | null => {
    if (/\bERROR\b|\[ERROR\]|ERROR\]/.test(line)) return 'ERROR';
    if (/\bWARN\b|\[WARN\]|WARN\]/.test(line)) return 'WARN';
    if (/\bINFO\b|\[INFO\]|INFO\]/.test(line)) return 'INFO';

    return null;
};

const highlight = (line: string, needle: string): React.ReactNode => {
    if (needle.length === 0) return line;
    const lower = line.toLowerCase();
    const target = needle.toLowerCase();
    const parts: React.ReactNode[] = [];
    let cursor = 0;
    let marks = 0;
    while (marks < 20) {
        const at = lower.indexOf(target, cursor);
        if (at < 0) break;
        if (at > cursor) parts.push(line.slice(cursor, at));
        parts.push(<mark key={at}>{line.slice(at, at + target.length)}</mark>);
        cursor = at + target.length;
        marks += 1;
        if (target.length === 0) break;
    }
    if (cursor < line.length) parts.push(line.slice(cursor));

    return parts;
};

const safeShareUrl = (id: string, url: string): string | null => {
    if (!/^[A-Za-z0-9]{4,16}$/.test(id)) return null;
    const expected = 'https://mclo.gs/' + id;

    return url === expected ? expected : null;
};

interface ShownLine {
    text: string;
    level: Level | 'other';
}

const visibleLines = (raw: string, filter: LevelFilter, needle: string): ShownLine[] => {
    const rows = raw === '' ? [] : raw.split('\n');
    const out: ShownLine[] = [];
    let active: Level | 'other' = 'other';
    let keep = false;
    rows.forEach((line) => {
        const found = lineLevel(line);
        const textOk = needle === '' || line.toLowerCase().indexOf(needle) !== -1;
        if (found) {
            active = found;
            keep = (filter === 'all' || found === filter) && textOk;
        } else {
            const levelOk = filter === 'all' || active === filter;
            keep = levelOk && (keep || textOk);
        }
        if (keep) out.push({ text: line, level: found || (active === 'other' ? 'other' : active) });
    });

    return out;
};

const Page = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const t = useToolsT();
    const { toasts, push, dismiss } = useToasts();
    const canRead = !!meta.permissions.read_content;
    const canList = !!meta.permissions.read;
    const [log, setLog] = useState<LogView | null>(null);
    const [reports, setReports] = useState<CrashReport[] | null>(null);
    const [crashNote, setCrashNote] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [opened, setOpened] = useState<string | null>(null);
    const [filter, setFilter] = useState<LevelFilter>('all');
    const [query, setQuery] = useState('');
    const [follow, setFollow] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const [tick, setTick] = useState(0);
    const [busy, setBusy] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const [agreed, setAgreed] = useState(false);
    const [shared, setShared] = useState<string | null>(null);
    const box = useRef<HTMLPreElement>(null);
    const quiet = useRef(false);

    useEffect(() => {
        if (!canRead) return undefined;
        let alive = true;
        if (opened === null) {
            const background = quiet.current;
            quiet.current = false;
            getLog(server)
                .then((view) => {
                    if (!alive) return;
                    setLog(view);
                    setError(null);
                })
                .catch((problem) => {
                    if (!alive || background) return;
                    setError(explain(problem));
                });
        }

        return () => {
            alive = false;
        };
    }, [server, attempt, tick, canRead, opened]);

    useEffect(() => {
        if (!canList) return undefined;
        let alive = true;
        getCrashes(server)
            .then((data) => alive && setReports(data.reports))
            .catch((problem) => alive && setCrashNote(explain(problem, tt('logs_crashes_failed'))));

        return () => {
            alive = false;
        };
    }, [server, attempt, canList]);

    useEffect(() => {
        if (!follow || opened !== null || !canRead) return undefined;
        const timer = window.setInterval(() => {
            quiet.current = true;
            setTick((value) => value + 1);
        }, 8000);

        return () => window.clearInterval(timer);
    }, [follow, opened, canRead]);

    const raw = log ? log.text || '' : '';
    const needle = query.trim().toLowerCase();
    const lines = useMemo(() => visibleLines(raw, filter, needle), [raw, filter, needle]);
    const shown = lines.map((line) => line.text).join('\n');

    useEffect(() => {
        if (follow && box.current) box.current.scrollTop = box.current.scrollHeight;
    }, [follow, shown]);

    const openCrash = async (file: string) => {
        setBusy(true);
        setError(null);
        try {
            const view = await getCrash(server, file);
            setLog(view);
            setOpened(file);
            setFollow(false);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const share = async () => {
        if (shown.trim() === '') {
            push('danger', t('logs_empty_title'));

            return;
        }
        let bytes = shown.length;
        try {
            bytes = new TextEncoder().encode(shown).length;
        } catch (ignored) {
            bytes = shown.length;
        }
        if (bytes > 1048576 || lines.length > 10000) {
            push('danger', t('logs_too_long'));

            return;
        }
        setBusy(true);
        try {
            const result = await shareLog(server, shown);
            const url = safeShareUrl(result.id, result.url);
            if (!url) {
                push('danger', t('generic_error'));
            } else {
                setShared(url);
                setShareOpen(false);
                setAgreed(false);
                push('ok', t('logs_shared'));
            }
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const copyLink = async () => {
        if (!shared) return;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(shared);
                push('ok', t('logs_copied'));

                return;
            }
        } catch (ignored) {
            // The field below stays selectable when the browser blocks the clipboard.
        }
        push('info', shared);
    };

    if (!canRead && !canList) {
        return (
            <Empty icon={'warn'} title={t('need_content_title')}>
                {t('need_content_text')}
            </Empty>
        );
    }

    return (
        <>
            <Toasts toasts={toasts} dismiss={dismiss} />
            <div className={'techtyl-tools-top'}>
                <p>{t('logs_intro')}</p>
                <Button small look={'ghost'} onClick={() => setAttempt((value) => value + 1)}>
                    <Icon id={'refresh'} size={16} />
                    {t('refresh')}
                </Button>
            </div>
            {!canRead && (
                <Notice tone={'info'} title={t('need_content_title')}>
                    {t('need_content_text')}
                </Notice>
            )}
            {canRead && error && (
                <Empty icon={'warn'} title={t('load_failed')} action={<Button onClick={() => setAttempt((value) => value + 1)}>{t('retry')}</Button>}>
                    {error}
                </Empty>
            )}
            {(canList || (canRead && !error)) && (
                <div className={'techtyl-tools-loglayout'}>
                    <div>
            {canRead && !error && !log && (
                <div className={'techtyl-tools-panel'}>
                    <SkeletonRows rows={5} />
                </div>
            )}
            {canRead && log && log.missing && (
                <Empty icon={'box'} title={t('logs_missing_title')}>
                    {t('logs_missing_text')}
                </Empty>
            )}
            {canRead && log && log.too_large && (
                <Empty icon={'warn'} title={t('logs_too_large_title')}>
                    {t('logs_too_large_text')}
                </Empty>
            )}
            {canRead && log && !log.missing && !log.too_large && (
                    <div className={'techtyl-tools-panel'}>
                        <div className={'techtyl-tools-panel-title'}>
                            <h2>{opened ? t('logs_crash') : t('logs_latest')}</h2>
                            <span className={'techtyl-tools-muted'}>
                                {formatBytes(log.bytes)} · {t('logs_lines', { count: lines.length })}
                            </span>
                        </div>
                        {log.truncated && <Notice tone={'info'}>{t('logs_truncated')}</Notice>}
                        {opened && (
                            <div style={{ margin: '8px 0' }}>
                                <Button small look={'ghost'} onClick={() => { setOpened(null); setLog(null); setAttempt((value) => value + 1); }}>
                                    {t('logs_back')}
                                </Button>
                            </div>
                        )}
                        <div className={'techtyl-tools-toolbar'}>
                            <div className={'techtyl-tools-search'}>
                                <Icon id={'search'} size={18} />
                                <input type={'search'} value={query} maxLength={80} placeholder={t('logs_search')} aria-label={t('logs_search')} autoComplete={'off'} onChange={(event) => setQuery(event.target.value)} />
                            </div>
                            <div className={'techtyl-tools-seg'} role={'group'} aria-label={t('logs_level')}>
                                {LEVELS.map((level) => (
                                    <button key={level} type={'button'} aria-pressed={filter === level} onClick={() => setFilter(level)}>
                                        {level === 'all' ? t('logs_level_all') : level}
                                    </button>
                                ))}
                            </div>
                            {!opened && (
                                <Button small look={follow ? 'primary' : 'ghost'} aria-pressed={follow} onClick={() => setFollow((on) => !on)}>
                                    {t('logs_follow')}
                                </Button>
                            )}
                            <Button
                                small
                                look={'plain'}
                                disabled={!log.sharing || shown.trim() === '' || busy}
                                onClick={() => {
                                    setAgreed(false);
                                    setShareOpen(true);
                                }}
                            >
                                {t('logs_share')}
                            </Button>
                        </div>
                        {!log.sharing && <p className={'techtyl-tools-muted'}>{t('logs_share_off')}</p>}
                        {raw.trim() === '' ? (
                            <Empty icon={'box'} title={t('logs_empty_title')}>
                                {t('logs_empty_text')}
                            </Empty>
                        ) : lines.length === 0 ? (
                            <p className={'techtyl-tools-muted'}>{t('logs_no_match')}</p>
                        ) : (
                            <pre className={'techtyl-tools-log'} ref={box} aria-label={t('logs_shown')}>
                                {lines.map((line, index) => (
                                    <div key={index} className={'techtyl-tools-log-line'} data-level={line.level === 'other' ? undefined : line.level}>
                                        {highlight(line.text, needle)}
                                    </div>
                                ))}
                            </pre>
                        )}
                    </div>
            )}
                    </div>
                    <div className={'techtyl-tools-panel'}>
                        <div className={'techtyl-tools-panel-title'}>
                            <h2>{t('logs_crashes')}</h2>
                        </div>
                        {!canList && <p className={'techtyl-tools-muted'}>{t('logs_no_list')}</p>}
                        {canList && crashNote && <p className={'techtyl-tools-muted'}>{crashNote}</p>}
                        {canList && reports && reports.length === 0 && <p className={'techtyl-tools-muted'}>{t('logs_crashes_empty')}</p>}
                        {canList &&
                            reports &&
                            reports.map((report) => (
                                <button key={report.name} type={'button'} className={cx('techtyl-tools-crash')} aria-current={opened === report.name} disabled={!canRead || busy} onClick={() => openCrash(report.name)}>
                                    <strong>{report.name}</strong>
                                    <span className={'techtyl-tools-muted'}>
                                        {formatBytes(report.size)} · {formatDate(report.modified)}
                                    </span>
                                </button>
                            ))}
                    </div>
                </div>
            )}
            {shareOpen && (
                <Dialog
                    title={t('logs_share_title')}
                    onClose={() => setShareOpen(false)}
                    footer={
                        <>
                            <Button look={'ghost'} onClick={() => setShareOpen(false)}>
                                {t('cancel')}
                            </Button>
                            <Button look={'primary'} busy={busy} disabled={!agreed} onClick={share}>
                                {t('logs_share_go')}
                            </Button>
                        </>
                    }
                >
                    <p>{t('logs_share_text')}</p>
                    <p style={{ marginTop: 8 }}>{t('logs_share_mask')}</p>
                    <p className={'techtyl-tools-muted'} style={{ marginTop: 8 }}>
                        {t('logs_lines', { count: lines.length })}
                    </p>
                    <label className={'techtyl-tools-checkline'}>
                        <input type={'checkbox'} checked={agreed} onChange={(event) => setAgreed(event.target.checked)} />
                        <span>{t('logs_share_public')}</span>
                    </label>
                </Dialog>
            )}
            {shared && (
                <Dialog
                    title={t('logs_shared')}
                    onClose={() => setShared(null)}
                    footer={
                        <>
                            <Button look={'ghost'} onClick={copyLink}>
                                {t('logs_copy')}
                            </Button>
                            <a className={'techtyl-tools-btn techtyl-tools-btn-primary'} href={shared} target={'_blank'} rel={'noreferrer noopener'}>
                                {t('logs_open_link')}
                            </a>
                        </>
                    }
                >
                    <div className={'techtyl-tools-field'}>
                        <input type={'text'} readOnly value={shared} aria-label={t('logs_open_link')} onFocus={(event) => event.currentTarget.select()} />
                    </div>
                </Dialog>
            )}
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_logs')}>
            <ToolsGate tool={'logs'}>{(meta, server) => <Page meta={meta} server={server} />}</ToolsGate>
        </ServerContentBlock>
    );
};
