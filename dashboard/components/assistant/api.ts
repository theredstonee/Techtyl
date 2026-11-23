import http, { httpErrorToHuman } from '@/api/http';

const BASE = '/api/client/extensions/techtyl/assistant';

export type MessageStatus = 'ok' | 'stopped' | 'length' | 'refusal' | 'error';

export interface ChatMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    status: MessageStatus;
    contextLines: number | null;
    createdAt: Date | null;
}

export interface Conversation {
    id: number;
    title: string;
    server: string | null;
    shareContext: boolean;
    updatedAt: Date;
}

export interface SelectableServer {
    id: string;
    label: string;
    egg: string;
}

export interface Usage {
    daily: number;
    usedToday: number;
}

export interface AssistantState {
    configured: boolean;
    provider: string | null;
    model: string | null;
    demo: boolean;
    isAdmin: boolean;
    adminUrl: string | null;
    contextEnabled: boolean;
    contextLines: number;
    maxInputChars: number;
    retentionDays: number;
    usage: Usage;
    servers: SelectableServer[];
    conversations: Conversation[];
}

const toDate = (value: any): Date | null => {
    const date = typeof value === 'string' ? new Date(value) : null;

    return date && !isNaN(date.getTime()) ? date : null;
};

const toMessage = (data: any): ChatMessage => ({
    id: Number(data.id),
    role: data.role === 'assistant' ? 'assistant' : 'user',
    content: String(data.content ?? ''),
    status: ['ok', 'stopped', 'length', 'refusal', 'error'].indexOf(data.status) !== -1 ? data.status : 'ok',
    contextLines: typeof data.context_lines === 'number' ? data.context_lines : null,
    createdAt: toDate(data.created_at),
});

const toConversation = (data: any): Conversation => ({
    id: Number(data.id),
    title: String(data.title ?? ''),
    server: typeof data.server === 'string' ? data.server : null,
    shareContext: data.share_context === true,
    updatedAt: toDate(data.updated_at) || new Date(),
});

const toUsage = (data: any): Usage => ({
    daily: Number(data?.daily ?? 0),
    usedToday: Number(data?.used_today ?? 0),
});

export const errorText = (error: any): string => httpErrorToHuman(error);

export const loadState = async (): Promise<AssistantState> => {
    const { data } = await http.get(`${BASE}/state`);
    const a = data.attributes;

    return {
        configured: a.configured === true,
        provider: a.provider ?? null,
        model: a.model ?? null,
        demo: a.demo === true,
        isAdmin: a.is_admin === true,
        adminUrl: typeof a.admin_url === 'string' ? a.admin_url : null,
        contextEnabled: a.context_enabled === true,
        contextLines: Number(a.context_lines ?? 0),
        maxInputChars: Number(a.limits?.max_input_chars ?? 4000),
        retentionDays: Number(a.limits?.retention_days ?? 0),
        usage: toUsage(a.limits),
        servers: (a.servers || []).map((s: any) => ({ id: String(s.id), label: String(s.name), egg: String(s.egg || '') })),
        conversations: (a.conversations || []).map(toConversation),
    };
};

export const loadConversations = async (): Promise<Conversation[]> => {
    const { data } = await http.get(`${BASE}/conversations`);

    return (data.data || []).map(toConversation);
};

export const loadConversation = async (id: number): Promise<{ conversation: Conversation; messages: ChatMessage[] }> => {
    const { data } = await http.get(`${BASE}/conversations/${id}`);

    return { conversation: toConversation(data.attributes), messages: (data.attributes.messages || []).map(toMessage) };
};

export const deleteConversation = async (id: number): Promise<void> => {
    await http.delete(`${BASE}/conversations/${id}`);
};

export const deleteAllConversations = async (): Promise<void> => {
    await http.delete(`${BASE}/conversations`);
};

export interface ChatRequest {
    message: string;
    conversation: number | null;
    server: string | null;
    shareContext: boolean;
}

export interface ChatHandlers {
    onMeta: (conversation: Conversation, contextLines: number | null) => void;
    onText: (piece: string) => void;
    onDone: (message: ChatMessage | null, usage: Usage) => void;
    onError: (text: string, partial: ChatMessage | null, usage: Usage | null, conversationRemoved: boolean) => void;
}

export type Translate = (key: string, replace?: Record<string, string | number>) => string;

/** The error carries a message that can be shown as it is. */
export class ChatError extends Error {
    status: number;

    constructor(text: string, status: number) {
        super(text);
        this.status = status;
    }
}

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const failure = async (response: Response, t: Translate): Promise<ChatError> => {
    let text = '';
    try {
        const data = await response.json();
        const first = Array.isArray(data?.errors) ? data.errors[0] : null;
        text = first && typeof first.detail === 'string' ? first.detail : '';
    } catch (e) {
        // Antwort ohne JSON: es bleibt bei der allgemeinen Meldung unten.
    }
    if (!text || /^[A-Za-z ]+\.?$/.test(text)) {
        if (response.status === 419 || response.status === 401) {
            text = t('error_session');
        } else if (response.status === 429) {
            text = t('error_too_many');
        } else if (response.status === 404) {
            text = t('error_unavailable');
        } else if (response.status >= 500 && !text) {
            text = t('error_server');
        } else if (!text) {
            text = t('error_rejected');
        }
    }

    return new ChatError(text, response.status);
};

const dispatch = (event: string, payload: any, handlers: ChatHandlers, t: Translate) => {
    if (event === 'meta') {
        handlers.onMeta(toConversation(payload.conversation), payload.question?.context_lines ?? null);
    } else if (event === 'delta') {
        if (typeof payload.text === 'string') handlers.onText(payload.text);
    } else if (event === 'done') {
        handlers.onDone(payload.message ? toMessage(payload.message) : null, toUsage(payload.usage));
    } else if (event === 'error') {
        handlers.onError(
            String(payload.message || t('error_default')),
            payload.partial ? toMessage(payload.partial) : null,
            payload.usage ? toUsage(payload.usage) : null,
            payload.conversation_removed === true
        );
    }
};

/**
 * Stellt eine Frage und liest die Antwort als Ereignisstrom. Bricht der Aufrufer ueber das Signal
 * ab, beendet der Server die Erzeugung und speichert, was bis dahin da war.
 */
export const streamChat = async (request: ChatRequest, handlers: ChatHandlers, signal: AbortSignal, t: Translate): Promise<void> => {
    const canStream = typeof ReadableStream !== 'undefined' && typeof TextDecoder !== 'undefined';
    const response = await fetch(`${BASE}/chat`, {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
            'Content-Type': 'application/json',
            // Nur JSON anfordern: Sonst leitet Laravel bei ungueltigen Eingaben um, statt den Fehler als JSON zu liefern.
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({
            message: request.message,
            conversation: request.conversation,
            server: request.server,
            share_context: request.shareContext,
            stream: canStream,
        }),
    });

    const type = response.headers.get('Content-Type') || '';
    if (type.indexOf('text/event-stream') === -1) {
        // Ohne Streaming kommt ein JSON-Dokument mit denselben Ereignissen.
        const data = await response.clone().json().catch(() => null);
        const events = data?.attributes;
        if (!events || (!response.ok && !events.error)) {
            throw await failure(response, t);
        }
        if (events.meta) dispatch('meta', events.meta, handlers, t);
        if (events.done?.message) handlers.onText(String(events.done.message.content || ''));
        if (events.done) dispatch('done', events.done, handlers, t);
        if (events.error) dispatch('error', events.error, handlers, t);

        return;
    }
    if (!response.ok || !response.body) {
        throw await failure(response, t);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';
    let finished = false;

    const flush = () => {
        let index = buffer.indexOf('\n\n');
        while (index !== -1) {
            const block = buffer.slice(0, index);
            buffer = buffer.slice(index + 2);
            let event = '';
            const lines: string[] = [];
            block.split('\n').forEach((line) => {
                if (line.indexOf('event:') === 0) event = line.slice(6).trim();
                else if (line.indexOf('data:') === 0) lines.push(line.slice(5).replace(/^ /, ''));
            });
            if (event && lines.length) {
                try {
                    dispatch(event, JSON.parse(lines.join('\n')), handlers, t);
                    if (event === 'done' || event === 'error') finished = true;
                } catch (e) {
                    // Ein unlesbares Ereignis wird uebergangen.
                }
            }
            index = buffer.indexOf('\n\n');
        }
    };

    for (;;) {
        const chunk = await reader.read();
        if (chunk.done) break;
        buffer += decoder.decode(chunk.value, { stream: true }).replace(/\r\n/g, '\n');
        flush();
    }
    buffer += decoder.decode();
    flush();

    if (!finished) {
        throw new ChatError(t('connection_lost'), 0);
    }
};
