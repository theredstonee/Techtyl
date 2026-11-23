import React, { useCallback, useEffect, useRef, useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/assistant';
import Markdown from '../../assistant/Markdown';
import {
    AssistantState,
    ChatError,
    ChatMessage,
    Conversation,
    deleteAllConversations,
    deleteConversation,
    errorText,
    loadConversation,
    loadState,
    MessageStatus,
    streamChat,
    Usage,
} from '../../assistant/api';
import '../../assistant/styles.css';

interface Bubble {
    key: string;
    role: 'user' | 'assistant';
    content: string;
    status: MessageStatus | 'streaming' | 'unsent';
    contextLines: number | null;
}

const EXAMPLES = ['ex1', 'ex2', 'ex3', 'ex4'];
const FOLLOW_UPS = ['follow1', 'follow2', 'follow3'];

const fromMessage = (message: ChatMessage): Bubble => ({
    key: `m${message.id}`,
    role: message.role,
    content: message.content,
    status: message.status,
    contextLines: message.contextLines,
});

const dayKey = (date: Date): string => {
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
    const time = date.getTime();
    if (time >= start) return 'day_today';
    if (time >= start - 86400000) return 'day_yesterday';
    if (time >= start - 6 * 86400000) return 'day_week';

    return 'day_older';
};

const percent = (usage: Usage): number => (usage.daily > 0 ? Math.min(100, Math.round((usage.usedToday / usage.daily) * 100)) : 0);

let counter = 0;
const nextKey = (): string => `n${Date.now()}-${counter++}`;

export default () => {
    const t = useT(texts);
    const [state, setState] = useState<AssistantState | null>(null);
    const [loadError, setLoadError] = useState('');
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [activeId, setActiveId] = useState<number | null>(null);
    const [bubbles, setBubbles] = useState<Bubble[]>([]);
    const [threadLoading, setThreadLoading] = useState(false);
    const [draft, setDraft] = useState('');
    const [server, setServer] = useState('');
    const [share, setShare] = useState(false);
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState<{ text: string; retry: string | null } | null>(null);
    const [usage, setUsage] = useState<Usage>({ daily: 0, usedToday: 0 });
    const [railOpen, setRailOpen] = useState(false);
    const [confirm, setConfirm] = useState<number | 'all' | null>(null);
    const [announce, setAnnounce] = useState('');

    const abort = useRef<AbortController | null>(null);
    const thread = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);
    const stick = useRef(true);
    const pending = useRef('');
    const frame = useRef(0);
    const streamKey = useRef('');
    const mounted = useRef(true);

    const refresh = useCallback(() => {
        return loadState().then((next) => {
            if (!mounted.current) return next;
            setState(next);
            setConversations(next.conversations);
            setUsage(next.usage);

            return next;
        });
    }, []);

    useEffect(() => {
        refresh().catch((error) => setLoadError(errorText(error)));

        return () => {
            mounted.current = false;
            window.cancelAnimationFrame(frame.current);
            if (abort.current) abort.current.abort();
        };
    }, []);

    // Mitscrollen, solange der Nutzer am Ende des Gespraechs steht.
    useEffect(() => {
        const element = thread.current;
        if (element && stick.current) element.scrollTop = element.scrollHeight;
    }, [bubbles]);

    useEffect(() => {
        const element = input.current;
        if (!element) return;
        element.style.height = 'auto';
        element.style.height = `${Math.min(220, element.scrollHeight + 2)}px`;
    }, [draft]);

    const onScroll = () => {
        const element = thread.current;
        if (element) stick.current = element.scrollHeight - element.scrollTop - element.clientHeight < 80;
    };

    const patchStream = (change: (bubble: Bubble) => Bubble | null) => {
        const key = streamKey.current;
        setBubbles((list) => {
            const out: Bubble[] = [];
            list.forEach((bubble) => {
                const next = bubble.key === key ? change(bubble) : bubble;
                if (next) out.push(next);
            });

            return out;
        });
    };

    const flushPending = () => {
        frame.current = 0;
        const piece = pending.current;
        if (!piece) return;
        pending.current = '';
        patchStream((bubble) => ({ ...bubble, content: bubble.content + piece }));
    };

    const newChat = () => {
        if (busy) return;
        setActiveId(null);
        setBubbles([]);
        setNote(null);
        setServer('');
        setShare(false);
        setRailOpen(false);
        setConfirm(null);
        stick.current = true;
        if (input.current) input.current.focus();
    };

    const open = (conversation: Conversation) => {
        if (busy || conversation.id === activeId) {
            setRailOpen(false);

            return;
        }
        setThreadLoading(true);
        setNote(null);
        setConfirm(null);
        setRailOpen(false);
        loadConversation(conversation.id)
            .then((result) => {
                if (!mounted.current) return;
                const known = state ? state.servers.some((s) => s.id === result.conversation.server) : false;
                setActiveId(result.conversation.id);
                setBubbles(result.messages.map(fromMessage));
                setServer(known && result.conversation.server ? result.conversation.server : '');
                setShare(known && result.conversation.shareContext && !!state && state.contextEnabled);
                stick.current = true;
            })
            .catch((error) => setNote({ text: errorText(error), retry: null }))
            .then(() => mounted.current && setThreadLoading(false));
    };

    const remove = (id: number | 'all') => {
        const job = id === 'all' ? deleteAllConversations() : deleteConversation(id);
        job.then(() => {
            if (!mounted.current) return;
            setConfirm(null);
            if (id === 'all') {
                setConversations([]);
                newChat();
                setAnnounce(t('deleted_all'));
            } else {
                setConversations((list) => list.filter((c) => c.id !== id));
                if (id === activeId) newChat();
                setAnnounce(t('deleted_one'));
            }
        }).catch((error) => setNote({ text: errorText(error), retry: null }));
    };

    const send = (raw: string) => {
        const text = raw.trim();
        if (!text || busy || !state || !state.configured) return;
        if (text.length > state.maxInputChars) {
            setNote({ text: t('too_long', { max: state.maxInputChars }), retry: null });

            return;
        }

        const questionKey = nextKey();
        const answerKey = nextKey();
        const controller = new AbortController();
        const startedIn = activeId;
        let received = false;
        let settled = false;
        abort.current = controller;
        streamKey.current = answerKey;
        pending.current = '';
        stick.current = true;
        setBusy(true);
        setNote(null);
        setDraft('');
        setAnnounce(t('announce_writing'));
        setBubbles((list) =>
            list
                .filter((bubble) => bubble.status !== 'unsent')
                .concat([
                    { key: questionKey, role: 'user', content: text, status: 'ok', contextLines: null },
                    { key: answerKey, role: 'assistant', content: '', status: 'streaming', contextLines: null },
                ])
        );

        const finish = () => {
            settled = true;
            window.cancelAnimationFrame(frame.current);
            flushPending();
            if (!mounted.current) return;
            setBusy(false);
            abort.current = null;
        };

        streamChat(
            { message: text, conversation: activeId, server: server || null, shareContext: share && !!server },
            {
                onMeta: (conversation, contextLines) => {
                    setActiveId(conversation.id);
                    setConversations((list) => [conversation].concat(list.filter((c) => c.id !== conversation.id)));
                    setBubbles((list) => list.map((bubble) => (bubble.key === questionKey ? { ...bubble, contextLines } : bubble)));
                },
                onText: (piece) => {
                    received = true;
                    pending.current += piece;
                    if (!frame.current) frame.current = window.requestAnimationFrame(flushPending);
                },
                onDone: (message, nextUsage) => {
                    finish();
                    setUsage(nextUsage);
                    patchStream((bubble) => (message ? { ...fromMessage(message), key: bubble.key } : bubble.content ? { ...bubble, status: 'stopped' } : null));
                    setAnnounce(t('announce_done'));
                },
                onError: (message, partial, nextUsage, conversationRemoved) => {
                    finish();
                    if (nextUsage) setUsage(nextUsage);
                    patchStream((bubble) => (partial ? { ...fromMessage(partial), key: bubble.key } : null));
                    if (!partial) {
                        setBubbles((list) => list.map((bubble) => (bubble.key === questionKey ? { ...bubble, status: 'unsent' } : bubble)));
                    }
                    if (conversationRemoved) {
                        setActiveId(startedIn);
                        refresh().catch(() => undefined);
                    }
                    setNote({ text: message, retry: partial ? null : text });
                    setAnnounce(message);
                },
            },
            controller.signal,
            t
        ).catch((error) => {
            if (settled) return;
            finish();
            if (!mounted.current) return;
            if (error && error.name === 'AbortError') {
                // Gestoppt: Der Server speichert, was bis dahin da war, und zaehlt die Anfrage.
                patchStream((bubble) => (bubble.content ? { ...bubble, status: 'stopped' } : null));
                setAnnounce(t('announce_stopped'));
                window.setTimeout(() => refresh().catch(() => undefined), 900);

                return;
            }
            if (received) {
                patchStream((bubble) => ({ ...bubble, status: 'error' }));
                setNote({ text: error instanceof ChatError ? error.message : t('connection_lost'), retry: null });
                window.setTimeout(() => refresh().catch(() => undefined), 900);

                return;
            }
            // Vor dem Start abgelehnt (Limit, Eingabe, Sitzung): nichts wurde gespeichert.
            setBubbles((list) => list.filter((bubble) => bubble.key !== questionKey && bubble.key !== answerKey));
            setDraft(text);
            const message = error instanceof ChatError ? error.message : t('no_connection');
            setNote({ text: message, retry: null });
            setAnnounce(message);
            if (error instanceof ChatError && error.status === 429) refresh().catch(() => undefined);
        });
    };

    const stop = () => {
        if (abort.current) abort.current.abort();
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
            event.preventDefault();
            send(draft);
        } else if (event.key === 'Escape' && busy) {
            event.preventDefault();
            stop();
        }
    };

    const pickExample = (example: string) => {
        setDraft(t(`${example}_text`));
        setNote(null);
        if (input.current) input.current.focus();
    };

    if (loadError) {
        return (
            <PageContentBlock title={t('title')}>
                <div className={'techtyl-assistant'}>
                    <div className={'techtyl-assistant-empty'}>
                        <h2>{t('load_failed')}</h2>
                        <p>{loadError}</p>
                        <button
                            type={'button'}
                            className={'techtyl-assistant-btn'}
                            onClick={() => {
                                setLoadError('');
                                refresh().catch((error) => setLoadError(errorText(error)));
                            }}
                        >
                            {t('retry')}
                        </button>
                    </div>
                </div>
            </PageContentBlock>
        );
    }

    if (!state) {
        return (
            <PageContentBlock title={t('title')}>
                <div className={'techtyl-assistant'} aria-busy={'true'}>
                    <div className={'techtyl-assistant-skeleton'}>
                        <span />
                        <span />
                        <span />
                    </div>
                </div>
            </PageContentBlock>
        );
    }

    const selected = state.servers.filter((s) => s.id === server)[0] || null;
    const used = percent(usage);
    const limitReached = usage.daily > 0 && usage.usedToday >= usage.daily;
    const sharing = share && !!selected && state.contextEnabled;
    const tooLong = draft.length > state.maxInputChars;
    const lastBubble = bubbles[bubbles.length - 1];
    const showFollowUps = !busy && !!lastBubble && lastBubble.role === 'assistant' && lastBubble.status === 'ok' && !limitReached;
    let lastDay = '';

    return (
        <PageContentBlock title={t('title')}>
            <div className={'techtyl-assistant'} data-rail={railOpen ? 'open' : 'closed'}>
                <header className={'techtyl-assistant-top'}>
                    <div>
                        <h1 className={'techtyl-assistant-sr'}>{t('title')}</h1>
                        <p className={'techtyl-assistant-muted'}>{t('subtitle')}</p>
                    </div>
                    {state.configured && (
                        <div className={'techtyl-assistant-chips'}>
                            <span className={'techtyl-assistant-chip'} title={t('chip_hint')}>
                                {state.demo ? t('demo_mode') : state.provider}
                                {!state.demo && state.model ? <b>{state.model}</b> : null}
                            </span>
                            <span
                                className={'techtyl-assistant-chip techtyl-assistant-meter'}
                                data-level={used >= 100 ? 'full' : used >= 75 ? 'high' : 'ok'}
                                title={t('meter_title', { used: usage.usedToday, daily: usage.daily })}
                            >
                                <i aria-hidden={'true'}>
                                    <i style={{ width: `${used}%` }} />
                                </i>
                                {t('meter')} <b>{used} %</b>
                            </span>
                            <button type={'button'} className={'techtyl-assistant-btn techtyl-assistant-railtoggle'} onClick={() => setRailOpen(!railOpen)} aria-expanded={railOpen}>
                                {t('conversations')}
                            </button>
                        </div>
                    )}
                </header>

                {!state.configured ? (
                    <div className={'techtyl-assistant-empty'}>
                        <h2>{t('setup_title')}</h2>
                        {state.isAdmin && state.adminUrl ? (
                            <>
                                <p>{t('setup_admin')}</p>
                                <a className={'techtyl-assistant-btn techtyl-assistant-btn-go'} href={state.adminUrl}>
                                    {t('setup_admin_link')}
                                </a>
                            </>
                        ) : (
                            <p>{t('setup_user')}</p>
                        )}
                    </div>
                ) : (
                    <div className={'techtyl-assistant-layout'}>
                        <aside className={'techtyl-assistant-rail'} aria-label={t('conversations')}>
                            <button type={'button'} className={'techtyl-assistant-btn techtyl-assistant-new'} onClick={newChat} disabled={busy}>
                                <span aria-hidden={'true'}>+</span> {t('new_chat')}
                            </button>
                            {conversations.length === 0 ? (
                                <p className={'techtyl-assistant-railhint'}>{t('rail_hint')}</p>
                            ) : (
                                <ul className={'techtyl-assistant-list'}>
                                    {conversations.map((conversation) => {
                                        const label = t(dayKey(conversation.updatedAt));
                                        const heading = label !== lastDay ? label : '';
                                        lastDay = label;

                                        return (
                                            <React.Fragment key={conversation.id}>
                                                {heading && <li className={'techtyl-assistant-day'}>{heading}</li>}
                                                <li className={'techtyl-assistant-item'} data-active={conversation.id === activeId ? 'yes' : 'no'}>
                                                    {confirm === conversation.id ? (
                                                        <div className={'techtyl-assistant-confirm'}>
                                                            <span>{t('confirm_delete')}</span>
                                                            <button type={'button'} onClick={() => remove(conversation.id)} className={'techtyl-assistant-danger'} autoFocus>
                                                                {t('yes')}
                                                            </button>
                                                            <button type={'button'} onClick={() => setConfirm(null)}>
                                                                {t('no')}
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <button
                                                                type={'button'}
                                                                className={'techtyl-assistant-open'}
                                                                onClick={() => open(conversation)}
                                                                disabled={busy}
                                                                aria-current={conversation.id === activeId ? 'true' : undefined}
                                                            >
                                                                {conversation.title}
                                                            </button>
                                                            <button
                                                                type={'button'}
                                                                className={'techtyl-assistant-trash'}
                                                                onClick={() => setConfirm(conversation.id)}
                                                                disabled={busy}
                                                                aria-label={t('delete_label', { title: conversation.title })}
                                                                title={t('delete_title')}
                                                            >
                                                                <svg viewBox={'0 0 16 16'} width={'14'} height={'14'} aria-hidden={'true'}>
                                                                    <path
                                                                        d={'M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5M6.8 7v3.5M9.2 7v3.5'}
                                                                        fill={'none'}
                                                                        stroke={'currentColor'}
                                                                        strokeWidth={'1.3'}
                                                                        strokeLinecap={'round'}
                                                                        strokeLinejoin={'round'}
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </>
                                                    )}
                                                </li>
                                            </React.Fragment>
                                        );
                                    })}
                                </ul>
                            )}
                            {conversations.length > 0 && (
                                <div className={'techtyl-assistant-railfoot'}>
                                    {confirm === 'all' ? (
                                        <div className={'techtyl-assistant-confirm'}>
                                            <span>{t('confirm_delete_all')}</span>
                                            <button type={'button'} onClick={() => remove('all')} className={'techtyl-assistant-danger'} autoFocus>
                                                {t('yes')}
                                            </button>
                                            <button type={'button'} onClick={() => setConfirm(null)}>
                                                {t('no')}
                                            </button>
                                        </div>
                                    ) : (
                                        <button type={'button'} className={'techtyl-assistant-textbtn'} onClick={() => setConfirm('all')} disabled={busy}>
                                            {t('delete_all')}
                                        </button>
                                    )}
                                    {state.retentionDays > 0 && <p>{t('retention', { days: state.retentionDays })}</p>}
                                </div>
                            )}
                        </aside>

                        <section className={'techtyl-assistant-chat'} aria-label={t('chat_label')}>
                            <div className={'techtyl-assistant-thread'} ref={thread} onScroll={onScroll} tabIndex={0} role={'log'} aria-live={'off'} aria-label={t('thread_label')}>
                                {threadLoading ? (
                                    <div className={'techtyl-assistant-skeleton'}>
                                        <span />
                                        <span />
                                    </div>
                                ) : bubbles.length === 0 ? (
                                    <div className={'techtyl-assistant-welcome'}>
                                        <h2>{t('welcome_title')}</h2>
                                        <p className={'techtyl-assistant-muted'}>{t('welcome_text')}</p>
                                        <div className={'techtyl-assistant-examples'}>
                                            {EXAMPLES.map((example, index) => (
                                                <button
                                                    type={'button'}
                                                    key={example}
                                                    className={'techtyl-assistant-example'}
                                                    style={{ animationDelay: `${index * 55}ms` }}
                                                    onClick={() => pickExample(example)}
                                                >
                                                    <strong>{t(`${example}_title`)}</strong>
                                                    <span>{t(`${example}_hint`)}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    bubbles.map((bubble) => (
                                        <div key={bubble.key} className={'techtyl-assistant-msg'} data-role={bubble.role} data-status={bubble.status}>
                                            <small>
                                                {bubble.role === 'user' ? t('you') : t('assistant')}
                                                {bubble.role === 'user' && bubble.contextLines !== null && (
                                                    <span className={'techtyl-assistant-tag'}>{t('tag_context', { lines: bubble.contextLines })}</span>
                                                )}
                                                {bubble.status === 'unsent' && <span className={'techtyl-assistant-tag'}>{t('tag_unsent')}</span>}
                                            </small>
                                            {bubble.role === 'user' ? (
                                                <div className={'techtyl-assistant-plain'}>{bubble.content}</div>
                                            ) : bubble.status === 'streaming' && !bubble.content ? (
                                                <div className={'techtyl-assistant-typing'} aria-label={t('typing')}>
                                                    <i />
                                                    <i />
                                                    <i />
                                                </div>
                                            ) : (
                                                <>
                                                    <Markdown source={bubble.content} />
                                                    {bubble.status === 'streaming' && <span className={'techtyl-assistant-caret'} aria-hidden={'true'} />}
                                                </>
                                            )}
                                            {bubble.status === 'stopped' && <p className={'techtyl-assistant-state'}>{t('state_stopped')}</p>}
                                            {bubble.status === 'length' && <p className={'techtyl-assistant-state'}>{t('state_length')}</p>}
                                            {bubble.status === 'error' && <p className={'techtyl-assistant-state'}>{t('state_error')}</p>}
                                            {bubble.status === 'refusal' && <p className={'techtyl-assistant-state'}>{t('state_refusal')}</p>}
                                        </div>
                                    ))
                                )}
                                {showFollowUps && (
                                    <div className={'techtyl-assistant-follow'}>
                                        {FOLLOW_UPS.map((follow) => (
                                            <button type={'button'} key={follow} className={'techtyl-assistant-btn techtyl-assistant-btn-sm'} onClick={() => send(t(follow))}>
                                                {t(follow)}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {note && (
                                <div className={'techtyl-assistant-note'} role={'alert'}>
                                    <span>{note.text}</span>
                                    {note.retry && !busy && (
                                        <button type={'button'} className={'techtyl-assistant-btn techtyl-assistant-btn-sm'} onClick={() => send(note.retry as string)}>
                                            {t('retry')}
                                        </button>
                                    )}
                                    <button type={'button'} className={'techtyl-assistant-textbtn'} onClick={() => setNote(null)} aria-label={t('close_label')}>
                                        {t('close')}
                                    </button>
                                </div>
                            )}

                            <form
                                className={'techtyl-assistant-composer'}
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    send(draft);
                                }}
                            >
                                <div className={'techtyl-assistant-context'}>
                                    <label className={'techtyl-assistant-select'}>
                                        <span>{t('server')}</span>
                                        <select
                                            value={server}
                                            disabled={busy || state.servers.length === 0}
                                            onChange={(event) => {
                                                setServer(event.target.value);
                                                // Die Freigabe gilt fuer genau einen Server und wird nie stillschweigend uebernommen.
                                                setShare(false);
                                            }}
                                        >
                                            <option value={''}>{state.servers.length === 0 ? t('no_servers') : t('without_server')}</option>
                                            {state.servers.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.label}
                                                    {s.egg ? ` (${s.egg})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    {state.contextEnabled && (
                                        <label className={'techtyl-assistant-switch'} data-disabled={!selected || busy ? 'yes' : 'no'}>
                                            <input type={'checkbox'} checked={sharing} disabled={!selected || busy} onChange={(event) => setShare(event.target.checked)} />
                                            <span aria-hidden={'true'} />
                                            {t('share')}
                                        </label>
                                    )}
                                </div>
                                {sharing && selected && (
                                    <p className={'techtyl-assistant-privacy'}>
                                        <strong>{t('privacy_label')}</strong>{' '}
                                        {t('privacy', {
                                            lines: state.contextLines,
                                            server: selected.label,
                                            target: state.demo ? t('privacy_target_demo') : t('privacy_target', { provider: state.provider || '' }),
                                        })}
                                    </p>
                                )}
                                <div className={'techtyl-assistant-row'}>
                                    <textarea
                                        ref={input}
                                        value={draft}
                                        rows={1}
                                        onChange={(event) => setDraft(event.target.value)}
                                        onKeyDown={onKeyDown}
                                        placeholder={limitReached ? t('placeholder_limit') : t('placeholder')}
                                        aria-label={t('question_label')}
                                        aria-invalid={tooLong}
                                        disabled={limitReached && !busy}
                                    />
                                    {busy ? (
                                        <button type={'button'} className={'techtyl-assistant-btn techtyl-assistant-stop'} onClick={stop}>
                                            <span aria-hidden={'true'} /> {t('stop')}
                                        </button>
                                    ) : (
                                        <button type={'submit'} className={'techtyl-assistant-btn techtyl-assistant-btn-go'} disabled={!draft.trim() || tooLong || limitReached}>
                                            {t('ask')}
                                        </button>
                                    )}
                                </div>
                                <div className={'techtyl-assistant-foot'}>
                                    <span>
                                        {limitReached
                                            ? t('foot_limit', { daily: usage.daily })
                                            : t('foot')}
                                    </span>
                                    {draft.length > state.maxInputChars * 0.8 && (
                                        <span data-over={tooLong ? 'yes' : 'no'}>
                                            {t('chars', { count: draft.length, max: state.maxInputChars })}
                                        </span>
                                    )}
                                </div>
                            </form>
                        </section>
                    </div>
                )}
                <div className={'techtyl-assistant-sr'} role={'status'} aria-live={'polite'}>
                    {announce}
                </div>
            </div>
        </PageContentBlock>
    );
};
