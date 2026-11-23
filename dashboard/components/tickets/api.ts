import http, { httpErrorToHuman } from '@/api/http';

const BASE = '/api/client/extensions/techtyl/tickets';

export type TicketStatus = 'open' | 'answered' | 'waiting' | 'closed';
export type StatusFilter = TicketStatus | 'all';

export interface TicketAttachment {
    id: number;
    url: string;
    width: number;
    height: number;
}

export interface TicketMessage {
    id: number;
    type: 'message' | 'closed' | 'reopened' | 'autoclosed' | 'status';
    from: 'you' | 'team' | 'system';
    teamName: string;
    body: string;
    createdAt: Date;
    attachments: TicketAttachment[];
    pending?: boolean;
}

export interface Ticket {
    id: number;
    subject: string;
    status: TicketStatus;
    category: { id: number; label: string } | null;
    server: { uuid: string; identifier: string; label: string } | null;
    unread: number;
    createdAt: Date;
    updatedAt: Date;
    closedBy: string | null;
    messages?: TicketMessage[];
}

export interface TicketMeta {
    categories: { id: number; label: string }[];
    servers: { uuid: string; identifier: string; label: string }[];
    limits: {
        subjectMin: number;
        subjectMax: number;
        bodyMax: number;
        maxOpen: number;
        openNow: number;
        attachments: boolean;
        maxFiles: number;
        maxUploadBytes: number;
        mimeTypes: string[];
    };
}

export interface TicketList {
    tickets: Ticket[];
    total: number;
    page: number;
    perPage: number;
    counts: Record<StatusFilter, number>;
    unread: number;
    openNow: number;
}

const toMessage = (raw: any): TicketMessage => ({
    id: raw.id,
    type: raw.type,
    from: raw.author,
    teamName: raw.author_name || '',
    body: raw.body || '',
    createdAt: new Date(raw.created_at),
    attachments: (raw.attachments || []).map((item: any) => ({
        id: item.id,
        url: item.url,
        width: item.width,
        height: item.height,
    })),
});

const toTicket = (raw: any): Ticket => {
    const a = raw.attributes;

    return {
        id: a.id,
        subject: a.subject,
        status: a.status,
        category: a.category ? { id: a.category.id, label: a.category.name } : null,
        server: a.server ? { uuid: a.server.uuid, identifier: a.server.identifier, label: a.server.name } : null,
        unread: a.unread,
        createdAt: new Date(a.created_at),
        updatedAt: new Date(a.updated_at),
        closedBy: a.closed_by,
        messages: a.messages ? a.messages.map(toMessage) : undefined,
    };
};

export const getMeta = async (): Promise<TicketMeta> => {
    const { data } = await http.get(`${BASE}/meta`);
    const a = data.attributes;

    return {
        categories: a.categories.map((item: any) => ({ id: item.id, label: item.name })),
        servers: a.servers.map((item: any) => ({ uuid: item.uuid, identifier: item.identifier, label: item.name })),
        limits: {
            subjectMin: a.limits.subject_min,
            subjectMax: a.limits.subject_max,
            bodyMax: a.limits.body_max,
            maxOpen: a.limits.max_open,
            openNow: a.limits.open_now,
            attachments: a.limits.attachments,
            maxFiles: a.limits.max_files,
            maxUploadBytes: a.limits.max_upload_bytes,
            mimeTypes: a.limits.mime_types,
        },
    };
};

export const listTickets = async (status: StatusFilter, search: string, page: number): Promise<TicketList> => {
    const params: Record<string, string | number> = { page };
    if (status !== 'all') params.status = status;
    if (search !== '') params.q = search;
    const { data } = await http.get(BASE, { params });

    return {
        tickets: data.data.map(toTicket),
        total: data.meta.total,
        page: data.meta.page,
        perPage: data.meta.per_page,
        counts: data.meta.counts,
        unread: data.meta.unread,
        openNow: data.meta.open_now,
    };
};

export const getTicket = async (id: number): Promise<Ticket> => {
    const { data } = await http.get(`${BASE}/${id}`);

    return toTicket(data);
};

/** Ohne Bilder als JSON, mit Bildern als Formular (der Browser setzt dann die Begrenzung selbst). */
const payload = (fields: Record<string, string>, files: File[]): FormData | Record<string, string> => {
    if (files.length === 0) return fields;
    const form = new FormData();
    Object.keys(fields).forEach((key) => form.append(key, fields[key]));
    files.forEach((file) => form.append('images[]', file));

    return form;
};

export interface NewTicket {
    subject: string;
    category: number;
    server: string;
    body: string;
}

export const createTicket = async (input: NewTicket, files: File[]): Promise<Ticket> => {
    const fields: Record<string, string> = {
        subject: input.subject,
        category: String(input.category),
        body: input.body,
    };
    if (input.server !== '') fields.server = input.server;
    const { data } = await http.post(BASE, payload(fields, files), { timeout: 60000 });

    return toTicket(data);
};

export const replyTicket = async (id: number, body: string, files: File[]): Promise<Ticket> => {
    const { data } = await http.post(`${BASE}/${id}/messages`, payload({ body }, files), { timeout: 60000 });

    return toTicket(data);
};

export const closeTicket = async (id: number): Promise<Ticket> => toTicket((await http.post(`${BASE}/${id}/close`)).data);

export const reopenTicket = async (id: number): Promise<Ticket> =>
    toTicket((await http.post(`${BASE}/${id}/reopen`)).data);

/** Fehlermeldung fuer Menschen: sagt, was los ist und was man tun kann. */
export const explain = (error: any, fallback: string): string => {
    const status = error && error.response ? error.response.status : 0;
    if (status === 429) return 'Das war zu schnell hintereinander. Warte einen Moment und versuch es dann noch einmal.';
    if (status === 413) return 'Die Anhänge sind zusammen zu groß für diesen Server. Nimm kleinere oder weniger Bilder.';
    if (status === 404) return 'Das Ticket wurde nicht gefunden. Vielleicht wurde es entfernt.';
    if (status === 401 || status === 419) return 'Deine Sitzung ist abgelaufen. Lade die Seite neu und melde dich wieder an.';
    if (status === 422 || status === 400 || status === 403) {
        const text = httpErrorToHuman(error);
        if (text) return text;
    }
    if (!status) return 'Keine Verbindung zum Panel. Prüfe deine Internetverbindung und versuch es erneut.';

    return fallback;
};
