import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ServerContext } from '@/state/server';
import { explain, getMeta, getTasks, statusOf, TaskOverview, ToolKey, ToolsMeta, ToolsTask } from './api';
import { currentLocale, tt, useToolsT } from './text';
import './styles.css';

/*
 * Gemeinsame Bausteine der Server-Werkzeuge.
 *
 * Eine neue Seite baut man so:
 *   const Page = () => (
 *       <ServerContentBlock title={'Spieler'}>
 *           <ToolsGate tool={'players'}>{(meta, server) => <Inhalt meta={meta} server={server} />}</ToolsGate>
 *       </ServerContentBlock>
 *   );
 * ToolsGate laedt GET /meta, zeigt Ladezustand, Fehler und den Hinweis "passt nicht zu diesem
 * Server" und rendert den Inhalt nur, wenn das Werkzeug verfuegbar ist.
 */

/* ------------------------------------------------------------------ Formatierung */

const numberLocale = (): string => (currentLocale() === 'de' ? 'de-DE' : 'en-US');
const decimal = (value: number, digits: number): string =>
    value.toLocaleString(numberLocale(), { minimumFractionDigits: digits, maximumFractionDigits: digits });

export const formatCount = (value: number): string => {
    if (value >= 1000000) return tt('count_million', { n: decimal(value / 1000000, value >= 10000000 ? 0 : 1) });
    if (value >= 10000) return tt('count_thousand', { n: Math.round(value / 1000) });

    return value.toLocaleString(numberLocale());
};

export const formatBytes = (bytes: number | null | undefined): string => {
    if (bytes === null || bytes === undefined) return '';
    if (bytes >= 1048576) return `${decimal(bytes / 1048576, 1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${bytes} B`;
};

export const formatDate = (value: string | null | undefined): string => {
    if (!value) return '';
    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) return '';
    const days = Math.floor((Date.now() - date.getTime()) / 86400000);
    if (days < 1) return tt('date_today');
    if (days === 1) return tt('date_yesterday');
    if (days < 30) return tt('date_days', { n: days });
    if (days < 365) return tt('date_months', { n: Math.floor(days / 30) });

    return date.toLocaleDateString(numberLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' });
};

export const cx = (...parts: (string | false | null | undefined)[]): string => parts.filter(Boolean).join(' ');

/* ------------------------------------------------------------------ Symbole (inline, ohne Fremddateien) */

const PATHS: Record<string, string> = {
    search: 'M11 4a7 7 0 1 0 4.4 12.5l4 4 1.4-1.4-4-4A7 7 0 0 0 11 4zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10z',
    download: 'M11 3h2v9.2l3.3-3.3 1.4 1.4L12 16l-5.7-5.7 1.4-1.4L11 12.2V3zM5 18h14v2H5v-2z',
    check: 'M9.5 16.2 5.3 12l-1.4 1.4 5.6 5.6 11-11-1.4-1.4-9.6 9.6z',
    close: 'M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6L19 6.4 17.6 5 12 10.6 6.4 5z',
    list: 'M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z',
    grid: 'M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z',
    heart: 'M12 20.5 4.6 13a4.9 4.9 0 0 1 7-6.9l.4.4.4-.4a4.9 4.9 0 0 1 7 6.9L12 20.5z',
    refresh: 'M12 5a7 7 0 0 1 6.3 4H15v2h6V5h-2v2.3A9 9 0 1 0 21 12h-2a7 7 0 1 1-7-7z',
    trash: 'M9 3h6l1 2h4v2H4V5h4l1-2zM6 9h12l-1 12H7L6 9zm4 2v8h1.5v-8H10zm2.5 0v8H14v-8h-1.5z',
    warn: 'M12 3 1.5 21h21L12 3zm-1 6h2v6h-2V9zm0 8h2v2h-2v-2z',
    info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm-1 4h2v2h-2V7zm0 4h2v6h-2v-6z',
    arrow: 'M13.2 5.3 11.8 6.7 16.1 11H4v2h12.1l-4.3 4.3 1.4 1.4L19.9 12l-6.7-6.7z',
    up: 'M12 4 5.3 10.7l1.4 1.4L11 7.8V20h2V7.8l4.3 4.3 1.4-1.4L12 4z',
    power: 'M11 3h2v9h-2V3zm5.7 2.9 1.4-1.4A9 9 0 1 1 5.9 4.5l1.4 1.4a7 7 0 1 0 9.4 0z',
    box: 'M12 2 3 6.5v11L12 22l9-4.5v-11L12 2zm0 2.2 6 3-6 3-6-3 6-3zM5 9.1l6 3v7.3l-6-3V9.1zm8 10.3v-7.3l6-3v7.3l-6 3z',
    external: 'M5 5h6v2H7v10h10v-4h2v6H5V5zm8 0h6v6h-2V8.4l-6.3 6.3-1.4-1.4L15.6 7H13V5z',
    clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 2a7 7 0 1 1 0 14 7 7 0 0 1 0-14zm-1 2v6l4.5 2.7 1-1.7-3.5-2.1V7h-2z',
};

export const Icon = ({ id, size = 16 }: { id: keyof typeof PATHS | string; size?: number }) => (
    <svg className={'techtyl-tools-icon'} width={size} height={size} viewBox={'0 0 24 24'} aria-hidden={'true'} focusable={'false'}>
        <path d={PATHS[id] || ''} fill={'currentColor'} />
    </svg>
);

/** Dialoge und Meldungen haengen im Modal-Bereich des Panels, damit die Formularstile des Themes greifen. */
const portalTarget = (): HTMLElement => document.getElementById('modal-portal') || document.body;

/* ------------------------------------------------------------------ Kleinteile */

export const Spinner = ({ label }: { label?: string }) => (
    <span className={'techtyl-tools-spinner'} role={'status'}>
        <span className={'techtyl-tools-spinner-ring'} aria-hidden={'true'} />
        {label ? <span>{label}</span> : <span className={'techtyl-tools-sr'}>{tt('loading')}</span>}
    </span>
);

export const Bar = ({ value, label }: { value: number; label?: string }) => {
    const percent = Math.max(0, Math.min(100, Math.round(value)));

    return (
        <div
            className={'techtyl-tools-bar'}
            role={'progressbar'}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            aria-label={label || tt('progress')}
        >
            <span style={{ width: `${percent}%` }} />
        </div>
    );
};

interface NoticeProps {
    tone?: 'info' | 'warning' | 'danger' | 'ok';
    title?: string;
    children?: React.ReactNode;
    action?: React.ReactNode;
}

export const Notice = ({ tone = 'info', title, children, action }: NoticeProps) => (
    <div className={cx('techtyl-tools-notice', `techtyl-tools-notice-${tone}`)} role={tone === 'danger' ? 'alert' : 'note'}>
        <Icon id={tone === 'ok' ? 'check' : tone === 'info' ? 'info' : 'warn'} size={18} />
        <div className={'techtyl-tools-notice-text'}>
            {title && <strong>{title}</strong>}
            {children && <div>{children}</div>}
        </div>
        {action && <div className={'techtyl-tools-notice-action'}>{action}</div>}
    </div>
);

interface EmptyProps {
    icon?: string;
    title: string;
    children?: React.ReactNode;
    action?: React.ReactNode;
}

export const Empty = ({ icon = 'box', title, children, action }: EmptyProps) => (
    <div className={'techtyl-tools-empty'}>
        <span className={'techtyl-tools-empty-icon'}>
            <Icon id={icon} size={26} />
        </span>
        <h3>{title}</h3>
        {children && <p>{children}</p>}
        {action}
    </div>
);

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    look?: 'primary' | 'plain' | 'ghost' | 'danger';
    small?: boolean;
    busy?: boolean;
}

export const Button = ({ look = 'plain', small, busy, className, children, disabled, ...rest }: ButtonProps) => (
    <button
        type={'button'}
        {...rest}
        disabled={disabled || busy}
        className={cx('techtyl-tools-btn', `techtyl-tools-btn-${look}`, small && 'techtyl-tools-btn-small', busy && 'techtyl-tools-btn-busy', className)}
    >
        {busy && <span className={'techtyl-tools-spinner-ring'} aria-hidden={'true'} />}
        {children}
    </button>
);

interface SegmentedProps<T extends string> {
    label: string;
    value: T;
    options: { id: T; label: React.ReactNode; title?: string }[];
    onChange: (value: T) => void;
}

export function Segmented<T extends string>({ label, value, options, onChange }: SegmentedProps<T>) {
    return (
        <div className={'techtyl-tools-seg'} role={'group'} aria-label={label}>
            {options.map((option) => (
                <button
                    key={option.id}
                    type={'button'}
                    aria-pressed={option.id === value}
                    title={option.title}
                    onClick={() => onChange(option.id)}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

/** Projektbild: nur von cdn.modrinth.com (prueft schon der Server), sonst Anfangsbuchstaben. */
export const ProjectIcon = ({ url, title, size = 44 }: { url: string | null; title: string; size?: number }) => {
    const [broken, setBroken] = useState(false);
    const safe = !!url && /^https:\/\/cdn\.modrinth\.com\//.test(url) && !broken;
    const letters = (title || '?').replace(/[^A-Za-z0-9ÄÖÜäöü]/g, '').slice(0, 2) || '?';
    let hue = 0;
    for (let i = 0; i < title.length; i++) hue = (hue * 31 + title.charCodeAt(i)) % 360;

    return (
        <span
            className={'techtyl-tools-picon'}
            style={{ width: size, height: size, fontSize: Math.round(size * 0.38), backgroundColor: safe ? undefined : `hsl(${hue} 42% 38%)` }}
            aria-hidden={'true'}
        >
            {safe ? (
                <img src={url as string} alt={''} width={size} height={size} loading={'lazy'} referrerPolicy={'no-referrer'} onError={() => setBroken(true)} />
            ) : (
                letters
            )}
        </span>
    );
};

export const SkeletonRows = ({ rows = 6, cards = false }: { rows?: number; cards?: boolean }) => (
    <div className={cx('techtyl-tools-results', cards && 'techtyl-tools-results-cards')} aria-hidden={'true'}>
        {Array.from({ length: rows }).map((_, index) => (
            <div key={index} className={'techtyl-tools-item techtyl-tools-skeleton'} style={{ animationDelay: `${index * 70}ms` }}>
                <span className={'techtyl-tools-sk techtyl-tools-sk-icon'} />
                <span className={'techtyl-tools-sk-lines'}>
                    <span className={'techtyl-tools-sk'} style={{ width: `${34 + ((index * 17) % 30)}%` }} />
                    <span className={'techtyl-tools-sk'} style={{ width: `${62 + ((index * 11) % 30)}%` }} />
                </span>
                <span className={'techtyl-tools-sk techtyl-tools-sk-btn'} />
            </div>
        ))}
    </div>
);

/* ------------------------------------------------------------------ Dialog */

interface DialogProps {
    title: string;
    onClose: () => void;
    wide?: boolean;
    children: React.ReactNode;
    footer?: React.ReactNode;
    labelledBy?: string;
}

/** Modaler Dialog: Escape schliesst, der Fokus bleibt im Dialog und kehrt danach zurueck. */
export const Dialog = ({ title, onClose, wide, children, footer }: DialogProps) => {
    const panel = useRef<HTMLDivElement>(null);
    const close = useRef(onClose);
    close.current = onClose;

    useEffect(() => {
        const previous = document.activeElement as HTMLElement | null;
        const node = panel.current;
        if (node) node.focus();
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close.current();

                return;
            }
            if (event.key !== 'Tab' || !node) return;
            const items = Array.from(
                node.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), [tabindex="0"]')
            ).filter((item) => item.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === node)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', onKey, true);

        return () => {
            document.removeEventListener('keydown', onKey, true);
            document.body.style.overflow = overflow;
            if (previous && previous.focus) previous.focus();
        };
    }, []);

    return createPortal(
        <div className={'techtyl-tools techtyl-tools-overlay'} onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div
                ref={panel}
                className={cx('techtyl-tools-dialog', wide && 'techtyl-tools-dialog-wide')}
                role={'dialog'}
                aria-modal={'true'}
                aria-label={title}
                tabIndex={-1}
            >
                <header className={'techtyl-tools-dialog-head'}>
                    <h2>{title}</h2>
                    <button type={'button'} className={'techtyl-tools-x'} onClick={onClose} aria-label={tt('close')}>
                        <Icon id={'close'} size={18} />
                    </button>
                </header>
                <div className={'techtyl-tools-dialog-body'}>{children}</div>
                {footer && <footer className={'techtyl-tools-dialog-foot'}>{footer}</footer>}
            </div>
        </div>,
        portalTarget()
    );
};

/* ------------------------------------------------------------------ Rueckmeldungen */

export interface Toast {
    id: number;
    tone: 'ok' | 'danger' | 'info';
    text: string;
}

export const useToasts = () => {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const counter = useRef(0);
    const mounted = useRef(true);
    useEffect(
        () => () => {
            mounted.current = false;
        },
        []
    );

    const dismiss = useCallback((id: number) => setToasts((list) => list.filter((toast) => toast.id !== id)), []);
    const push = useCallback(
        (tone: Toast['tone'], text: string) => {
            const id = ++counter.current;
            setToasts((list) => [...list.slice(-3), { id, tone, text }]);
            window.setTimeout(() => mounted.current && dismiss(id), tone === 'danger' ? 9000 : 5000);
        },
        [dismiss]
    );

    return { toasts, push, dismiss };
};

export const Toasts = ({ toasts, dismiss }: { toasts: Toast[]; dismiss: (id: number) => void }) =>
    createPortal(
    <div className={'techtyl-tools techtyl-tools-toasts'} aria-live={'polite'}>
        {toasts.map((toast) => (
            <div key={toast.id} className={cx('techtyl-tools-toast', `techtyl-tools-toast-${toast.tone}`)}>
                <Icon id={toast.tone === 'ok' ? 'check' : toast.tone === 'danger' ? 'warn' : 'info'} size={18} />
                <span>{toast.text}</span>
                <button type={'button'} className={'techtyl-tools-x'} onClick={() => dismiss(toast.id)} aria-label={tt('close_message')}>
                    <Icon id={'close'} size={14} />
                </button>
            </div>
        ))}
    </div>,
        portalTarget()
    );

/* ------------------------------------------------------------------ Server und Meta */

export const useServer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);

    return { uuid, status };
};

interface GateProps {
    tool: ToolKey;
    /** Ueberschrift des Hinweises, wenn das Werkzeug nicht zum Server passt. */
    unavailable?: (meta: ToolsMeta) => { title: string; text: string } | null;
    children: (meta: ToolsMeta, server: string, reload: () => void) => React.ReactNode;
}

export const ToolsGate = ({ tool, unavailable, children }: GateProps) => {
    const { uuid } = useServer();
    const t = useToolsT();
    const [meta, setMeta] = useState<ToolsMeta | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        let alive = true;
        setError(null);
        getMeta(uuid)
            .then((data) => alive && setMeta(data))
            .catch((problem) => {
                if (!alive) return;
                setError(statusOf(problem) === 404 ? tt('module_off') : explain(problem));
            });

        return () => {
            alive = false;
        };
    }, [uuid, attempt]);

    const reload = useCallback(() => setAttempt((value) => value + 1), []);

    if (error) {
        return (
            <div className={'techtyl-tools'}>
                <Empty icon={'warn'} title={t('load_failed')} action={<Button onClick={reload}>{t('retry')}</Button>}>
                    {error}
                </Empty>
            </div>
        );
    }
    if (!meta) {
        return (
            <div className={'techtyl-tools'}>
                <div className={'techtyl-tools-panel'}>
                    <SkeletonRows rows={5} />
                </div>
            </div>
        );
    }
    if (!meta.tools[tool] || !meta.tools[tool].available) {
        const custom = unavailable ? unavailable(meta) : null;
        const fallback = meta.minecraft
            ? { title: t('gate_other_title', { type: meta.type_label }), text: t('gate_other_text', { egg: meta.egg }) }
            : { title: t('gate_none_title'), text: t('gate_none_text', { egg: meta.egg }) };
        const shown = custom || fallback;

        return (
            <div className={'techtyl-tools'}>
                <Empty icon={'info'} title={shown.title}>
                    {shown.text}
                </Empty>
            </div>
        );
    }

    return <div className={'techtyl-tools'}>{children(meta, uuid, reload)}</div>;
};

/* ------------------------------------------------------------------ Vorgaenge */

interface TaskHook {
    active: ToolsTask[];
    recent: ToolsTask[];
    loaded: boolean;
    /** Einen eben gestarteten Vorgang sofort anzeigen. */
    track: (task: ToolsTask) => void;
    refresh: () => void;
}

/**
 * Laufende und zuletzt beendete Vorgaenge des Servers. Solange etwas laeuft, wird regelmaessig
 * nachgefragt; onFinished meldet jeden Vorgang genau einmal, wenn er fertig oder gescheitert ist
 * (auch nach einem Seitenwechsel, solange er beim Zurueckkommen noch lief).
 */
export const useTasks = (server: string, onFinished?: (task: ToolsTask) => void): TaskHook => {
    const [state, setState] = useState<TaskOverview>({ active: [], recent: [] });
    const [loaded, setLoaded] = useState(false);
    const watching = useRef<Record<string, true>>({});
    const callback = useRef(onFinished);
    callback.current = onFinished;
    const timer = useRef<number | undefined>(undefined);
    const alive = useRef(true);
    const failures = useRef(0);

    const load = useCallback(() => {
        window.clearTimeout(timer.current);
        getTasks(server)
            .then((data) => {
                if (!alive.current) return;
                failures.current = 0;
                const activeIds: Record<string, true> = {};
                data.active.forEach((task) => {
                    activeIds[task.uuid] = true;
                });
                Object.keys(watching.current).forEach((uuid) => {
                    if (activeIds[uuid]) return;
                    delete watching.current[uuid];
                    const finished = data.recent.find((task) => task.uuid === uuid);
                    if (finished && callback.current) callback.current(finished);
                });
                data.active.forEach((task) => {
                    watching.current[task.uuid] = true;
                });
                setState(data);
                setLoaded(true);
                if (data.active.length > 0) timer.current = window.setTimeout(load, 1300);
            })
            .catch(() => {
                if (!alive.current) return;
                setLoaded(true);
                failures.current += 1;
                if (Object.keys(watching.current).length > 0) {
                    timer.current = window.setTimeout(load, Math.min(15000, 2000 * failures.current));
                }
            });
    }, [server]);

    useEffect(() => {
        alive.current = true;
        load();

        return () => {
            alive.current = false;
            window.clearTimeout(timer.current);
        };
    }, [load]);

    const track = useCallback(
        (task: ToolsTask) => {
            watching.current[task.uuid] = true;
            setState((current) => ({ ...current, active: [...current.active.filter((item) => item.uuid !== task.uuid), task] }));
            window.clearTimeout(timer.current);
            timer.current = window.setTimeout(load, 500);
        },
        [load]
    );

    return { active: state.active, recent: state.recent, loaded, track, refresh: load };
};
