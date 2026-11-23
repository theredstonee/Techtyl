import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useHistory, useLocation } from 'react-router-dom';
import PageContentBlock from '@/components/elements/PageContentBlock';
import {
    closeTicket,
    createTicket,
    explain,
    getMeta,
    getTicket,
    listTickets,
    reopenTicket,
    replyTicket,
    StatusFilter,
    Ticket,
    TicketList,
    TicketMessage,
    TicketMeta,
    TicketStatus,
} from '../../tickets/api';
import '../../tickets/styles.css';

/* ------------------------------------------------------------------ Texte und Hilfen */

const STATUS_LABEL: Record<TicketStatus, string> = {
    open: 'Offen',
    answered: 'Antwort vom Team',
    waiting: 'Wartet auf dich',
    closed: 'Geschlossen',
};

const FILTERS: { key: StatusFilter; label: string }[] = [
    { key: 'all', label: 'Alle' },
    { key: 'answered', label: 'Antwort vom Team' },
    { key: 'waiting', label: 'Wartet auf dich' },
    { key: 'open', label: 'Offen' },
    { key: 'closed', label: 'Geschlossen' },
];

const pad = (value: number) => (value < 10 ? '0' : '') + value;
const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
const dayStart = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
const daysAgo = (date: Date) => Math.round((dayStart(new Date()) - dayStart(date)) / 86400000);
const fullDate = (date: Date) => `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
const WEEKDAYS = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

/** Kurz fuer die Liste: "gerade eben", "vor 12 Min.", "13:20", "gestern", "Montag", "03.11.2026". */
const shortTime = (date: Date): string => {
    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    if (minutes < 1) return 'gerade eben';
    if (minutes < 60) return `vor ${minutes} Min.`;
    const days = daysAgo(date);
    if (days <= 0) return clock(date);
    if (days === 1) return 'gestern';
    if (days < 7) return WEEKDAYS[date.getDay()];

    return fullDate(date);
};

/** Fuer Nachrichten: "heute 13:20", "gestern 13:20", "03.11.2026 13:20". */
const longTime = (date: Date): string => {
    const days = daysAgo(date);
    if (days <= 0) return `heute ${clock(date)}`;
    if (days === 1) return `gestern ${clock(date)}`;

    return `${fullDate(date)} ${clock(date)}`;
};

/* Entwuerfe ueberleben den Wechsel zwischen Tickets und ein Neuladen des Tabs (sessionStorage);
   angehaengte Bilder bleiben, solange die Seite offen ist. */
const DRAFT_PREFIX = 'techtyl-tickets-draft-';
const fileDrafts: Record<string, File[]> = {};

const readDraft = (key: string): string => {
    try {
        return window.sessionStorage.getItem(DRAFT_PREFIX + key) || '';
    } catch (e) {
        return '';
    }
};

const writeDraft = (key: string, value: string) => {
    try {
        if (value === '') window.sessionStorage.removeItem(DRAFT_PREFIX + key);
        else window.sessionStorage.setItem(DRAFT_PREFIX + key, value);
    } catch (e) {
        // Ohne Speicher bleibt der Entwurf nur im geoeffneten Formular.
    }
};

const useDraft = (key: string): [string, (value: string) => void] => {
    const [value, setValue] = useState(() => readDraft(key));
    const set = useCallback(
        (next: string) => {
            setValue(next);
            writeDraft(key, next);
        },
        [key]
    );

    return [value, set];
};

/* ------------------------------------------------------------------ kleine Bausteine */

const StatusPill = ({ status }: { status: TicketStatus }) => (
    <span className={'techtyl-tickets-state'} data-status={status}>
        {STATUS_LABEL[status]}
    </span>
);

const Notice = ({ children, onRetry }: { children: React.ReactNode; onRetry?: () => void }) => (
    <div className={'techtyl-tickets-alert'} role={'alert'}>
        <span>{children}</span>
        {onRetry && (
            <button type={'button'} className={'techtyl-tickets-btn'} data-size={'small'} onClick={onRetry}>
                Erneut versuchen
            </button>
        )}
    </div>
);

/** Nutzertext: nur Text. Adressen mit http(s) werden zu Links, alles andere bleibt Buchstabe fuer Buchstabe. */
const MessageText = ({ text }: { text: string }) => {
    const parts: React.ReactNode[] = [];
    const pattern = /https?:\/\/[^\s<>"'`]+/gi;
    let last = 0;
    let match = pattern.exec(text);
    while (match !== null) {
        const raw = match[0].replace(/[.,;:!?)\]]+$/, '');
        let safe = false;
        try {
            const parsed = new URL(raw);
            safe = parsed.protocol === 'https:' || parsed.protocol === 'http:';
        } catch (e) {
            safe = false;
        }
        if (safe) {
            if (match.index > last) parts.push(text.slice(last, match.index));
            parts.push(
                <a key={match.index} href={raw} target={'_blank'} rel={'noopener noreferrer nofollow'}>
                    {raw}
                </a>
            );
            last = match.index + raw.length;
        }
        match = pattern.exec(text);
    }
    if (last < text.length) parts.push(text.slice(last));

    return <p className={'techtyl-tickets-text'}>{parts}</p>;
};

const eventText = (message: TicketMessage): string => {
    const team = message.from === 'team';
    switch (message.type) {
        case 'closed':
            return team ? 'Das Team hat das Ticket geschlossen' : 'Du hast das Ticket geschlossen';
        case 'reopened':
            return team ? 'Das Team hat das Ticket wieder geöffnet' : 'Du hast das Ticket wieder geöffnet';
        case 'autoclosed':
            return 'Automatisch geschlossen, weil länger keine Antwort kam';
        default:
            return `Neuer Status: ${STATUS_LABEL[message.body as TicketStatus] || 'geändert'}`;
    }
};

/* ------------------------------------------------------------------ Anhaenge */

interface PickerProps {
    meta: TicketMeta;
    files: File[];
    onChange: (files: File[]) => void;
    onProblem: (text: string) => void;
    disabled: boolean;
}

/** Prueft neue Bilder gegen die Grenzen des Servers, bevor sie hochgeladen werden. */
const mergeFiles = (meta: TicketMeta, current: File[], added: File[]): { files: File[]; problem: string } => {
    const limits = meta.limits;
    const files = current.slice();
    let problem = '';
    added.forEach((file) => {
        if (limits.mimeTypes.indexOf(file.type) < 0) {
            const kinds = limits.mimeTypes.map((type) => type.replace('image/', '').toUpperCase()).join(', ');
            problem = `„${file.name}“ passt nicht. Möglich sind hier Bilder als ${kinds}.`;
        } else if (file.size > limits.maxUploadBytes) {
            problem = `„${file.name}“ ist zu groß (höchstens ${Math.floor(limits.maxUploadBytes / 1024)} KB).`;
        } else if (files.length >= limits.maxFiles) {
            problem = `Höchstens ${limits.maxFiles} Bilder je Nachricht.`;
        } else {
            files.push(file);
        }
    });

    return { files, problem };
};

const Thumb = ({ file, onRemove, disabled }: { file: File; onRemove: () => void; disabled: boolean }) => {
    const url = useMemo(() => URL.createObjectURL(file), [file]);
    useEffect(() => () => URL.revokeObjectURL(url), [url]);

    return (
        <li className={'techtyl-tickets-thumb'}>
            <img src={url} alt={''} />
            <button type={'button'} onClick={onRemove} disabled={disabled} aria-label={`${file.name} entfernen`}>
                <svg viewBox={'0 0 12 12'} width={'10'} height={'10'} aria-hidden={'true'}>
                    <path d={'M2 2l8 8M10 2l-8 8'} stroke={'currentColor'} strokeWidth={'1.8'} strokeLinecap={'round'} />
                </svg>
            </button>
        </li>
    );
};

const Picker = ({ meta, files, onChange, onProblem, disabled }: PickerProps) => {
    const input = useRef<HTMLInputElement>(null);
    if (!meta.limits.attachments || meta.limits.mimeTypes.length === 0) return null;

    const add = (list: FileList | null) => {
        if (!list || list.length === 0) return;
        const result = mergeFiles(meta, files, Array.prototype.slice.call(list));
        onChange(result.files);
        onProblem(result.problem);
    };

    return (
        <div className={'techtyl-tickets-picker'}>
            <input
                ref={input}
                type={'file'}
                accept={meta.limits.mimeTypes.join(',')}
                multiple
                hidden
                onChange={(event) => {
                    add(event.currentTarget.files);
                    event.currentTarget.value = '';
                }}
            />
            {files.length > 0 && (
                <ul className={'techtyl-tickets-thumbs'}>
                    {files.map((file, index) => (
                        <Thumb
                            key={`${file.name}-${file.size}-${index}`}
                            file={file}
                            disabled={disabled}
                            onRemove={() => onChange(files.filter((unused, position) => position !== index))}
                        />
                    ))}
                </ul>
            )}
            <button
                type={'button'}
                className={'techtyl-tickets-btn'}
                data-size={'small'}
                disabled={disabled || files.length >= meta.limits.maxFiles}
                onClick={() => input.current && input.current.click()}
            >
                <svg viewBox={'0 0 16 16'} width={'14'} height={'14'} aria-hidden={'true'}>
                    <path
                        d={'M13 7.5l-5.2 5.2a3 3 0 01-4.3-4.3l5.6-5.6a2 2 0 012.9 2.9L6.5 11.2a1 1 0 01-1.5-1.5L9.8 5'}
                        fill={'none'}
                        stroke={'currentColor'}
                        strokeWidth={'1.4'}
                        strokeLinecap={'round'}
                    />
                </svg>
                Bild anhängen
            </button>
        </div>
    );
};

const pastedImages = (event: React.ClipboardEvent): File[] => {
    const items = event.clipboardData ? event.clipboardData.files : null;

    return items ? Array.prototype.slice.call(items).filter((file: File) => file.type.indexOf('image/') === 0) : [];
};

/* ------------------------------------------------------------------ Neues Ticket */

interface NewDraft {
    subject: string;
    category: string;
    server: string;
    body: string;
}

const emptyDraft: NewDraft = { subject: '', category: '', server: '', body: '' };

const NewTicketForm = ({
    meta,
    onCreated,
    onCancel,
}: {
    meta: TicketMeta;
    onCreated: (ticket: Ticket) => void;
    onCancel: () => void;
}) => {
    const [stored, setStored] = useDraft('new');
    const draft: NewDraft = useMemo(() => {
        try {
            return { ...emptyDraft, ...(stored ? JSON.parse(stored) : {}) };
        } catch (e) {
            return emptyDraft;
        }
    }, [stored]);
    const [files, setFilesState] = useState<File[]>(fileDrafts.new || []);
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState('');
    const subjectInput = useRef<HTMLInputElement>(null);

    const update = (change: Partial<NewDraft>) => setStored(JSON.stringify({ ...draft, ...change }));
    const setFiles = (next: File[]) => {
        fileDrafts.new = next;
        setFilesState(next);
    };

    useEffect(() => {
        if (subjectInput.current) subjectInput.current.focus();
    }, []);

    const limits = meta.limits;
    const category = draft.category || (meta.categories.length === 1 ? String(meta.categories[0].id) : '');
    const full = limits.openNow >= limits.maxOpen;
    const subject = draft.subject.trim();
    const ready =
        !busy && !full && subject.length >= limits.subjectMin && category !== '' && draft.body.trim().length > 0;
    const remaining = limits.bodyMax - draft.body.length;

    const submit = () => {
        if (!ready) return;
        setBusy(true);
        setProblem('');
        createTicket({ subject, category: Number(category), server: draft.server, body: draft.body }, files)
            .then((ticket) => {
                setStored('');
                fileDrafts.new = [];
                onCreated(ticket);
            })
            .catch((error) => {
                setProblem(explain(error, 'Das Ticket konnte nicht eröffnet werden. Versuch es gleich noch einmal.'));
                setBusy(false);
            });
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <form
            className={'techtyl-tickets-new'}
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            onKeyDown={onKeyDown}
            noValidate
        >
            <div className={'techtyl-tickets-head'}>
                <button type={'button'} className={'techtyl-tickets-back'} onClick={onCancel} aria-label={'Zurück zur Liste'}>
                    <svg viewBox={'0 0 16 16'} width={'16'} height={'16'} aria-hidden={'true'}>
                        <path d={'M10 3L5 8l5 5'} fill={'none'} stroke={'currentColor'} strokeWidth={'1.8'} strokeLinecap={'round'} strokeLinejoin={'round'} />
                    </svg>
                </button>
                <div>
                    <h2>Ticket eröffnen</h2>
                    <p className={'techtyl-tickets-muted'}>Beschreib kurz, worum es geht. Das Team antwortet hier.</p>
                </div>
            </div>

            <div className={'techtyl-tickets-form'}>
                {full && (
                    <Notice>
                        Du hast bereits {limits.openNow} offene Tickets (höchstens {limits.maxOpen}). Schließe ein
                        erledigtes Ticket oder antworte in einem bestehenden.
                    </Notice>
                )}
                {meta.categories.length === 0 && (
                    <Notice>Das Team hat noch keine Kategorien angelegt. Tickets sind deshalb gerade nicht möglich.</Notice>
                )}

                <label className={'techtyl-tickets-label'}>
                    <span>Betreff</span>
                    <input
                        ref={subjectInput}
                        className={'techtyl-tickets-field'}
                        type={'text'}
                        value={draft.subject}
                        maxLength={limits.subjectMax}
                        placeholder={'Zum Beispiel: Server startet nach Update nicht'}
                        onChange={(event) => update({ subject: event.currentTarget.value })}
                        disabled={busy}
                    />
                </label>

                <div className={'techtyl-tickets-pair'}>
                    <label className={'techtyl-tickets-label'}>
                        <span>Kategorie</span>
                        <select
                            className={'techtyl-tickets-field'}
                            value={category}
                            onChange={(event) => update({ category: event.currentTarget.value })}
                            disabled={busy}
                        >
                            <option value={''} disabled>
                                Bitte wählen
                            </option>
                            {meta.categories.map((item) => (
                                <option key={item.id} value={String(item.id)}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className={'techtyl-tickets-label'}>
                        <span>
                            Server <em>optional</em>
                        </span>
                        <select
                            className={'techtyl-tickets-field'}
                            value={draft.server}
                            onChange={(event) => update({ server: event.currentTarget.value })}
                            disabled={busy || meta.servers.length === 0}
                        >
                            <option value={''}>{meta.servers.length === 0 ? 'Du hast noch keinen Server' : 'Kein bestimmter Server'}</option>
                            {meta.servers.map((item) => (
                                <option key={item.uuid} value={item.uuid}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <label className={'techtyl-tickets-label'}>
                    <span>Nachricht</span>
                    <textarea
                        className={'techtyl-tickets-field'}
                        rows={8}
                        value={draft.body}
                        maxLength={limits.bodyMax}
                        placeholder={'Was ist passiert, was hast du erwartet? Fehlermeldungen am besten wörtlich.'}
                        onChange={(event) => update({ body: event.currentTarget.value })}
                        onPaste={(event) => {
                            const images = pastedImages(event);
                            if (images.length > 0 && limits.attachments) {
                                event.preventDefault();
                                const result = mergeFiles(meta, files, images);
                                setFiles(result.files);
                                setProblem(result.problem);
                            }
                        }}
                        disabled={busy}
                    />
                </label>
                <p className={'techtyl-tickets-muted techtyl-tickets-safety'}>
                    Schick keine Passwörter. Das Team fragt nie danach.
                    {remaining <= 500 && <span> Noch {remaining} Zeichen.</span>}
                </p>

                <Picker meta={meta} files={files} onChange={setFiles} onProblem={setProblem} disabled={busy} />

                {problem !== '' && <Notice>{problem}</Notice>}

                <div className={'techtyl-tickets-actions'}>
                    <span className={'techtyl-tickets-muted techtyl-tickets-keys'}>
                        <kbd>Strg</kbd> + <kbd>Enter</kbd> sendet
                    </span>
                    <button type={'button'} className={'techtyl-tickets-btn'} onClick={onCancel} disabled={busy}>
                        Abbrechen
                    </button>
                    <button type={'submit'} className={'techtyl-tickets-btn'} data-kind={'go'} disabled={!ready}>
                        {busy ? 'Wird gesendet …' : 'Ticket eröffnen'}
                    </button>
                </div>
            </div>
        </form>
    );
};

/* ------------------------------------------------------------------ Gespraech */

interface ThreadProps {
    id: number;
    meta: TicketMeta;
    onChanged: (ticket: Ticket) => void;
    onBack: () => void;
}

let pendingId = -1;

const Thread = ({ id, meta, onChanged, onBack }: ThreadProps) => {
    const key = `t${id}`;
    const [ticket, setTicket] = useState<Ticket | null>(null);
    const [messages, setMessages] = useState<TicketMessage[]>([]);
    const [loadError, setLoadError] = useState('');
    const [problem, setProblem] = useState('');
    const [text, setText] = useDraft(key);
    const [files, setFilesState] = useState<File[]>(fileDrafts[key] || []);
    const [sending, setSending] = useState(false);
    const [working, setWorking] = useState(false);
    const [confirmClose, setConfirmClose] = useState(false);
    const confirmShown = useRef(0);
    const scroller = useRef<HTMLDivElement>(null);
    const textarea = useRef<HTMLTextAreaElement>(null);
    const stick = useRef(true);
    const alive = useRef(true);
    const sendingRef = useRef(false);
    // Nur wirklich neu hinzugekommene Nachrichten werden eingeblendet, nicht der ganze Verlauf beim Oeffnen.
    const known = useRef<number[] | null>(null);
    const fresh = useRef<number[]>([]);

    const setFiles = (next: File[]) => {
        fileDrafts[key] = next;
        setFilesState(next);
    };

    const apply = useCallback(
        (next: Ticket, animate: boolean) => {
            if (!alive.current) return;
            const ids = (next.messages || []).map((message) => message.id);
            const before = known.current;
            if (animate && before !== null) {
                fresh.current = fresh.current.concat(ids.filter((item) => before.indexOf(item) < 0));
            }
            known.current = ids;
            setTicket(next);
            setMessages(next.messages || []);
            onChanged(next);
        },
        [onChanged]
    );

    const load = useCallback(
        (quiet: boolean) => {
            getTicket(id)
                .then((next) => {
                    // Waehrend des Sendens nicht ueberschreiben: die eigene Nachricht steht schon vorlaeufig da.
                    if (!sendingRef.current) apply(next, true);
                    if (alive.current) setLoadError('');
                })
                .catch((error) => {
                    if (alive.current && !quiet) {
                        setLoadError(explain(error, 'Das Ticket konnte nicht geladen werden.'));
                    }
                });
        },
        [id, apply]
    );

    useEffect(() => {
        alive.current = true;
        load(false);
        const timer = window.setInterval(() => {
            if (!document.hidden) load(true);
        }, 20000);

        return () => {
            alive.current = false;
            window.clearInterval(timer);
        };
    }, [load]);

    // Am Ende bleiben, wenn der Nutzer dort war; wer nach oben gelesen hat, wird nicht weggerissen.
    useEffect(() => {
        const node = scroller.current;
        if (node && stick.current) node.scrollTop = node.scrollHeight;
    }, [messages]);

    const onScroll = () => {
        const node = scroller.current;
        if (node) stick.current = node.scrollHeight - node.scrollTop - node.clientHeight < 80;
    };

    const body = text.trim();
    const canSend = !sending && body.length > 0 && body.length <= meta.limits.bodyMax;

    const send = () => {
        if (!canSend || !ticket) return;
        const sentText = text;
        const sentFiles = files;
        const optimistic: TicketMessage = {
            id: pendingId--,
            type: 'message',
            from: 'you',
            teamName: '',
            body,
            createdAt: new Date(),
            attachments: [],
            pending: true,
        };
        fresh.current = fresh.current.concat(optimistic.id);
        stick.current = true;
        sendingRef.current = true;
        setSending(true);
        setProblem('');
        setMessages((current) => current.concat(optimistic));
        setText('');
        setFiles([]);

        replyTicket(id, body, sentFiles)
            .then((next) => {
                sendingRef.current = false;
                apply(next, false);
            })
            .catch((error) => {
                sendingRef.current = false;
                if (!alive.current) {
                    // Die Ansicht wurde gewechselt: Entwurf zuruecklegen, damit nichts verloren geht.
                    writeDraft(key, sentText);
                    fileDrafts[key] = sentFiles;

                    return;
                }
                setMessages((current) => current.filter((message) => message.id !== optimistic.id));
                setText(sentText);
                setFiles(sentFiles);
                setProblem(explain(error, 'Die Nachricht wurde nicht gesendet. Dein Text ist noch da, versuch es noch einmal.'));
            })
            .then(() => {
                if (alive.current) {
                    setSending(false);
                    if (textarea.current) textarea.current.focus();
                }
            });
    };

    const change = (action: (ticketId: number) => Promise<Ticket>, fallback: string) => {
        setWorking(true);
        setProblem('');
        action(id)
            .then((next) => {
                stick.current = true;
                apply(next, true);
            })
            .catch((error) => alive.current && setProblem(explain(error, fallback)))
            .then(() => {
                if (alive.current) {
                    setWorking(false);
                    setConfirmClose(false);
                }
            });
    };

    if (!ticket) {
        return (
            <div className={'techtyl-tickets-thread'}>
                <div className={'techtyl-tickets-head'}>
                    <button type={'button'} className={'techtyl-tickets-back'} onClick={onBack} aria-label={'Zurück zur Liste'}>
                        <svg viewBox={'0 0 16 16'} width={'16'} height={'16'} aria-hidden={'true'}>
                            <path d={'M10 3L5 8l5 5'} fill={'none'} stroke={'currentColor'} strokeWidth={'1.8'} strokeLinecap={'round'} strokeLinejoin={'round'} />
                        </svg>
                    </button>
                    <div>
                        <h2>{loadError ? 'Ticket nicht geladen' : 'Ticket wird geladen'}</h2>
                    </div>
                </div>
                <div className={'techtyl-tickets-scroll'}>
                    {loadError ? (
                        <Notice onRetry={() => load(false)}>{loadError}</Notice>
                    ) : (
                        <div className={'techtyl-tickets-skeleton'} aria-hidden={'true'}>
                            <i />
                            <i />
                            <i />
                        </div>
                    )}
                </div>
            </div>
        );
    }

    const closed = ticket.status === 'closed';
    const remaining = meta.limits.bodyMax - text.length;
    const context = [ticket.category ? ticket.category.label : '', ticket.server ? ticket.server.label : '']
        .filter((part) => part !== '')
        .join(' · ');

    return (
        <div className={'techtyl-tickets-thread'}>
            <div className={'techtyl-tickets-head'}>
                <button type={'button'} className={'techtyl-tickets-back'} onClick={onBack} aria-label={'Zurück zur Liste'}>
                    <svg viewBox={'0 0 16 16'} width={'16'} height={'16'} aria-hidden={'true'}>
                        <path d={'M10 3L5 8l5 5'} fill={'none'} stroke={'currentColor'} strokeWidth={'1.8'} strokeLinecap={'round'} strokeLinejoin={'round'} />
                    </svg>
                </button>
                <div className={'techtyl-tickets-title'}>
                    <h2>{ticket.subject}</h2>
                    <p className={'techtyl-tickets-muted'}>
                        <span className={'techtyl-tickets-number'}>#{ticket.id}</span>
                        {context !== '' && <span>{context}</span>}
                        <StatusPill status={ticket.status} />
                    </p>
                </div>
                {!closed &&
                    (confirmClose ? (
                        <div className={'techtyl-tickets-confirm'}>
                            <span>Erledigt?</span>
                            <button
                                type={'button'}
                                className={'techtyl-tickets-btn'}
                                data-size={'small'}
                                data-kind={'go'}
                                disabled={working}
                                onClick={() => {
                                    // Ein Doppelklick auf "Schließen" darf nicht gleich bestaetigen.
                                    if (Date.now() - confirmShown.current > 450) {
                                        change(closeTicket, 'Das Ticket konnte nicht geschlossen werden.');
                                    }
                                }}
                                autoFocus
                            >
                                Ja, schließen
                            </button>
                            <button
                                type={'button'}
                                className={'techtyl-tickets-btn'}
                                data-size={'small'}
                                disabled={working}
                                onClick={() => setConfirmClose(false)}
                            >
                                Nein
                            </button>
                        </div>
                    ) : (
                        <button type={'button'} className={'techtyl-tickets-btn'} data-size={'small'}
                            onClick={() => {
                                confirmShown.current = Date.now();
                                setConfirmClose(true);
                            }}
                        >
                            Schließen
                        </button>
                    ))}
            </div>

            <div className={'techtyl-tickets-scroll'} ref={scroller} onScroll={onScroll} role={'log'} aria-label={'Verlauf'}>
                {messages.map((message) => {
                    if (message.type !== 'message') {
                        return (
                            <p
                                key={message.id}
                                className={'techtyl-tickets-event'}
                                data-fresh={fresh.current.indexOf(message.id) >= 0 ? 'true' : undefined}
                            >
                                <span>
                                    {eventText(message)} · {longTime(message.createdAt)}
                                </span>
                            </p>
                        );
                    }
                    const mine = message.from === 'you';
                    const who = mine ? 'Du' : message.teamName ? `${message.teamName} vom Team` : 'Team';

                    return (
                        <div
                            key={message.id}
                            className={'techtyl-tickets-msg'}
                            data-from={mine ? 'me' : 'team'}
                            data-pending={message.pending ? 'true' : undefined}
                            data-fresh={fresh.current.indexOf(message.id) >= 0 ? 'true' : undefined}
                        >
                            <small>
                                {who} · {message.pending ? 'wird gesendet …' : longTime(message.createdAt)}
                            </small>
                            <MessageText text={message.body} />
                            {message.attachments.length > 0 && (
                                <div className={'techtyl-tickets-images'}>
                                    {message.attachments.map((image) => (
                                        <a key={image.id} href={image.url} target={'_blank'} rel={'noopener noreferrer'}>
                                            <img
                                                src={image.url}
                                                width={image.width}
                                                height={image.height}
                                                loading={'lazy'}
                                                alt={`Anhang, ${image.width} × ${image.height} Pixel, öffnet in neuem Tab`}
                                            />
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {closed ? (
                <div className={'techtyl-tickets-closed'}>
                    <p>
                        <b>Dieses Ticket ist geschlossen.</b>{' '}
                        <span className={'techtyl-tickets-muted'}>Ist das Thema doch noch offen, mach hier einfach weiter.</span>
                    </p>
                    {problem !== '' && <Notice>{problem}</Notice>}
                    <button
                        type={'button'}
                        className={'techtyl-tickets-btn'}
                        disabled={working}
                        onClick={() => change(reopenTicket, 'Das Ticket konnte nicht wieder geöffnet werden.')}
                    >
                        {working ? 'Einen Moment …' : 'Wieder öffnen'}
                    </button>
                </div>
            ) : (
                <form
                    className={'techtyl-tickets-composer'}
                    onSubmit={(event) => {
                        event.preventDefault();
                        send();
                    }}
                >
                    {problem !== '' && <Notice>{problem}</Notice>}
                    <textarea
                        ref={textarea}
                        className={'techtyl-tickets-field'}
                        rows={3}
                        value={text}
                        maxLength={meta.limits.bodyMax}
                        placeholder={ticket.status === 'open' ? 'Noch etwas ergänzen …' : 'Antwort schreiben …'}
                        aria-label={'Antwort'}
                        onChange={(event) => setText(event.currentTarget.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                                event.preventDefault();
                                send();
                            }
                        }}
                        onPaste={(event) => {
                            const images = pastedImages(event);
                            if (images.length > 0 && meta.limits.attachments) {
                                event.preventDefault();
                                const result = mergeFiles(meta, files, images);
                                setFiles(result.files);
                                setProblem(result.problem);
                            }
                        }}
                    />
                    <div className={'techtyl-tickets-actions'}>
                        <Picker meta={meta} files={files} onChange={setFiles} onProblem={setProblem} disabled={sending} />
                        <span className={'techtyl-tickets-muted techtyl-tickets-keys'}>
                            {remaining <= 500 ? (
                                `Noch ${remaining} Zeichen`
                            ) : (
                                <>
                                    <kbd>Strg</kbd> + <kbd>Enter</kbd> sendet
                                </>
                            )}
                        </span>
                        <button type={'submit'} className={'techtyl-tickets-btn'} data-kind={'go'} disabled={!canSend}>
                            Antworten
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
};

/* ------------------------------------------------------------------ Seite */

type Selection = number | 'new' | null;

const readSelection = (search: string): Selection => {
    const params = new URLSearchParams(search);
    if (params.get('neu') !== null) return 'new';
    const raw = params.get('ticket') || '';

    return /^[1-9][0-9]{0,9}$/.test(raw) ? Number(raw) : null;
};

export default () => {
    const history = useHistory();
    const location = useLocation();
    const [meta, setMeta] = useState<TicketMeta | null>(null);
    const [list, setList] = useState<TicketList | null>(null);
    const [listError, setListError] = useState('');
    const [filter, setFilter] = useState<StatusFilter>('all');
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [loadingMore, setLoadingMore] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [selected, setSelected] = useState<Selection>(() => readSelection(location.search));
    const request = useRef(0);
    const reload = useRef<(quiet: boolean) => void>(() => undefined);

    const select = useCallback(
        (next: Selection) => {
            setSelected(next);
            const target = next === null ? '' : next === 'new' ? '?neu' : `?ticket=${next}`;
            history.replace({ pathname: location.pathname, search: target });
        },
        [history, location.pathname]
    );

    const loadMeta = useCallback(() => {
        getMeta()
            .then(setMeta)
            .catch((error) => setListError(explain(error, 'Die Tickets konnten nicht geladen werden.')));
    }, []);

    useEffect(loadMeta, [loadMeta]);

    // Suche erst abschicken, wenn kurz nichts getippt wurde.
    useEffect(() => {
        const timer = window.setTimeout(() => setSearch(query.trim()), 250);

        return () => window.clearTimeout(timer);
    }, [query]);

    const loadList = useCallback(
        (quiet: boolean) => {
            const mine = ++request.current;
            if (!quiet) setRefreshing(true);
            listTickets(filter, search, 1)
                .then((next) => {
                    if (mine !== request.current) return;
                    setListError('');
                    setRefreshing(false);
                    setList((current) => {
                        if (!quiet || !current || current.tickets.length <= next.perPage) return next;
                        // Im Hintergrund aktualisiert: weiter unten bereits nachgeladene Tickets behalten.
                        const fresh = next.tickets.map((ticket) => ticket.id);

                        return {
                            ...next,
                            page: current.page,
                            tickets: next.tickets.concat(current.tickets.filter((ticket) => fresh.indexOf(ticket.id) < 0)),
                        };
                    });
                })
                .catch((error) => {
                    if (mine !== request.current) return;
                    setRefreshing(false);
                    if (!quiet) setListError(explain(error, 'Die Tickets konnten nicht geladen werden.'));
                });
        },
        [filter, search]
    );

    reload.current = loadList;

    useEffect(() => {
        loadList(false);
        const timer = window.setInterval(() => {
            if (!document.hidden) loadList(true);
        }, 30000);

        return () => window.clearInterval(timer);
    }, [loadList]);

    const loadMore = () => {
        if (!list || loadingMore) return;
        setLoadingMore(true);
        listTickets(filter, search, list.page + 1)
            .then((next) => {
                setList((current) => {
                    if (!current) return next;
                    const known = current.tickets.map((ticket) => ticket.id);

                    return { ...next, tickets: current.tickets.concat(next.tickets.filter((ticket) => known.indexOf(ticket.id) < 0)) };
                });
            })
            .catch((error) => setListError(explain(error, 'Weitere Tickets konnten nicht geladen werden.')))
            .then(() => setLoadingMore(false));
    };

    // Das geoeffnete Ticket hat sich geaendert (gelesen, beantwortet, geschlossen): Liste sofort angleichen.
    const onChanged = useCallback(
        (ticket: Ticket) => {
            setList((current) => {
                if (!current) return current;
                const before = current.tickets.filter((item) => item.id === ticket.id)[0];
                if (before && before.status === ticket.status && before.unread === ticket.unread && +before.updatedAt === +ticket.updatedAt) {
                    return current;
                }
                const summary = { ...ticket, messages: undefined };
                window.setTimeout(() => reload.current(true), 0);

                return { ...current, tickets: current.tickets.map((item) => (item.id === ticket.id ? summary : item)) };
            });
        },
        []
    );

    const onCreated = (ticket: Ticket) => {
        setFilter('all');
        setQuery('');
        setList((current) => (current ? { ...current, tickets: [{ ...ticket, messages: undefined }].concat(current.tickets) } : current));
        select(ticket.id);
        loadMeta();
        loadList(true);
    };

    const counts = list ? list.counts : null;
    const waiting = counts ? counts.answered + counts.waiting : 0;
    const subtitle = !list
        ? 'Fragen, Probleme, Wünsche: das Team hilft dir hier.'
        : waiting === 1
        ? '1 Ticket wartet auf deine Antwort'
        : waiting > 1
        ? `${waiting} Tickets warten auf deine Antwort`
        : counts && counts.open > 0
        ? 'Das Team ist am Zug. Neue Antworten erscheinen hier von selbst.'
        : 'Fragen, Probleme, Wünsche: das Team hilft dir hier.';

    const nothingAtAll = !!list && list.counts.all === 0 && search === '';
    const hasMore = !!list && list.tickets.length < list.total;

    return (
        <PageContentBlock title={'Tickets'}>
            <div className={'techtyl-tickets'} data-pane={selected === null ? 'list' : 'detail'}>
                <header className={'techtyl-tickets-top'}>
                    <div>
                        {/* Den sichtbaren Seitentitel setzt die Techtyl-Shell; diese Ueberschrift ist fuer Screenreader. */}
                        <h1 className={'techtyl-tickets-sr'}>Tickets</h1>
                        <p className={'techtyl-tickets-muted'} aria-live={'polite'}>
                            {subtitle}
                        </p>
                    </div>
                    <button
                        type={'button'}
                        className={'techtyl-tickets-btn'}
                        data-kind={'go'}
                        disabled={!meta}
                        onClick={() => select('new')}
                    >
                        Ticket eröffnen
                    </button>
                </header>

                <div className={'techtyl-tickets-grid'}>
                    <aside className={'techtyl-tickets-panel techtyl-tickets-side'} aria-label={'Deine Tickets'}>
                        <div className={'techtyl-tickets-tools'}>
                            <input
                                className={'techtyl-tickets-field'}
                                type={'search'}
                                value={query}
                                maxLength={120}
                                placeholder={'Im Betreff suchen'}
                                aria-label={'Im Betreff suchen'}
                                onChange={(event) => setQuery(event.currentTarget.value)}
                            />
                            <div className={'techtyl-tickets-filters'} role={'group'} aria-label={'Nach Status filtern'}>
                                {FILTERS.map((item) => (
                                    <button
                                        key={item.key}
                                        type={'button'}
                                        aria-pressed={filter === item.key}
                                        onClick={() => setFilter(item.key)}
                                    >
                                        {item.label}
                                        {counts && counts[item.key] > 0 && <span>{counts[item.key]}</span>}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className={'techtyl-tickets-items'} data-busy={refreshing ? 'true' : undefined}>
                            {listError !== '' && (
                                <Notice
                                    onRetry={() => {
                                        if (!meta) loadMeta();
                                        loadList(false);
                                    }}
                                >
                                    {listError}
                                </Notice>
                            )}
                            {!list && listError === '' && (
                                <div className={'techtyl-tickets-skeleton'} aria-hidden={'true'}>
                                    <i />
                                    <i />
                                    <i />
                                    <i />
                                </div>
                            )}
                            {list && list.tickets.length === 0 && (
                                <div className={'techtyl-tickets-empty'}>
                                    {nothingAtAll ? (
                                        <>
                                            <b>Noch keine Tickets</b>
                                            <p>Wenn etwas hakt oder du eine Frage hast, schreib dem Team.</p>
                                        </>
                                    ) : search !== '' ? (
                                        <>
                                            <b>Nichts gefunden</b>
                                            <p>Kein Betreff enthält „{search}“.</p>
                                            <button type={'button'} className={'techtyl-tickets-btn'} data-size={'small'} onClick={() => setQuery('')}>
                                                Suche leeren
                                            </button>
                                        </>
                                    ) : (
                                        <>
                                            <b>Hier ist nichts</b>
                                            <p>Kein Ticket mit dem Status „{STATUS_LABEL[filter as TicketStatus]}“.</p>
                                            <button type={'button'} className={'techtyl-tickets-btn'} data-size={'small'} onClick={() => setFilter('all')}>
                                                Alle zeigen
                                            </button>
                                        </>
                                    )}
                                </div>
                            )}
                            {list &&
                                list.tickets.map((ticket) => (
                                    <button
                                        key={ticket.id}
                                        type={'button'}
                                        className={'techtyl-tickets-item'}
                                        aria-current={selected === ticket.id ? 'true' : undefined}
                                        data-unread={ticket.unread > 0 ? 'true' : undefined}
                                        onClick={() => select(ticket.id)}
                                    >
                                        <span className={'techtyl-tickets-item-top'}>
                                            <b>{ticket.subject}</b>
                                            {ticket.unread > 0 && (
                                                <span className={'techtyl-tickets-count'} aria-label={`${ticket.unread} ungelesen`}>
                                                    {ticket.unread > 99 ? '99+' : ticket.unread}
                                                </span>
                                            )}
                                        </span>
                                        <span className={'techtyl-tickets-item-meta'}>
                                            {[ticket.category ? ticket.category.label : '', ticket.server ? ticket.server.label : '']
                                                .filter((part) => part !== '')
                                                .join(', ') || 'Ohne Kategorie'}
                                        </span>
                                        <span className={'techtyl-tickets-item-foot'}>
                                            <StatusPill status={ticket.status} />
                                            <time dateTime={ticket.updatedAt.toISOString()}>{shortTime(ticket.updatedAt)}</time>
                                        </span>
                                    </button>
                                ))}
                            {hasMore && (
                                <button type={'button'} className={'techtyl-tickets-btn techtyl-tickets-more'} disabled={loadingMore} onClick={loadMore}>
                                    {loadingMore ? 'Lädt …' : 'Weitere laden'}
                                </button>
                            )}
                        </div>
                    </aside>

                    <section className={'techtyl-tickets-panel techtyl-tickets-main'} aria-label={'Gespräch'}>
                        {selected === 'new' && meta && (
                            <NewTicketForm key={'new'} meta={meta} onCreated={onCreated} onCancel={() => select(null)} />
                        )}
                        {typeof selected === 'number' && meta && (
                            <Thread key={selected} id={selected} meta={meta} onChanged={onChanged} onBack={() => select(null)} />
                        )}
                        {selected !== null && !meta && listError === '' && (
                            <div className={'techtyl-tickets-skeleton'} aria-hidden={'true'}>
                                <i />
                                <i />
                                <i />
                            </div>
                        )}
                        {selected === null && (
                            <div className={'techtyl-tickets-welcome'}>
                                <svg viewBox={'0 0 64 64'} width={'56'} height={'56'} aria-hidden={'true'}>
                                    <path
                                        d={'M10 14h44a4 4 0 014 4v24a4 4 0 01-4 4H30l-12 9v-9h-8a4 4 0 01-4-4V18a4 4 0 014-4z'}
                                        fill={'none'}
                                        stroke={'currentColor'}
                                        strokeWidth={'2.5'}
                                        strokeLinejoin={'round'}
                                    />
                                    <path d={'M18 26h28M18 34h18'} stroke={'currentColor'} strokeWidth={'2.5'} strokeLinecap={'round'} />
                                </svg>
                                {nothingAtAll ? (
                                    <>
                                        <h2>Wie können wir helfen?</h2>
                                        <p className={'techtyl-tickets-muted'}>
                                            Eröffne ein Ticket und beschreib dein Anliegen. Die Antwort des Teams erscheint hier als Gespräch.
                                        </p>
                                        <button type={'button'} className={'techtyl-tickets-btn'} data-kind={'go'} disabled={!meta} onClick={() => select('new')}>
                                            Erstes Ticket eröffnen
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <h2>Wähl ein Ticket</h2>
                                        <p className={'techtyl-tickets-muted'}>
                                            Links stehen deine Tickets, ganz oben die mit neuen Antworten. Entwürfe bleiben erhalten, wenn du zwischen Tickets wechselst.
                                        </p>
                                    </>
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </PageContentBlock>
    );
};
