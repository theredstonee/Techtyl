// Client calls for folders, the personal arrangement, and power signals.
import http, { httpErrorToHuman } from '@/api/http';
import { Folder, LayoutId, NameIssue, PowerFailure, ServerFacts, Signal } from './types';

const BASE = '/api/client/extensions/techtyl';
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const record = (value: unknown): { [key: string]: unknown } | null => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    return value as { [key: string]: unknown };
};

export const sameId = (left: string, right: string): boolean => left.toLowerCase() === right.toLowerCase();

export const folderIssue = (value: string): NameIssue | null => {
    const trimmed = value.trim();
    if (trimmed.length < 1) return 'empty';
    if (Array.from(trimmed).length > 40) return 'long';
    if (trimmed.indexOf('<') !== -1 || trimmed.indexOf('>') !== -1) return 'chars';
    for (let i = 0; i < trimmed.length; i++) {
        const code = trimmed.charCodeAt(i);
        if (code < 32 || code === 127) return 'chars';
    }
    return null;
};

export const errorText = (error: unknown, fallback: string): string => {
    if (error instanceof Error && error.message === 'bad-shape') return fallback;
    try {
        const text = httpErrorToHuman(error);
        if (typeof text === 'string') {
            const clean = text.trim();
            if (clean !== '' && clean.indexOf('Request failed with status code') !== 0) return clean;
        }
    } catch (ignored) {
        return fallback;
    }
    return fallback;
};

export const parseFolder = (value: unknown): Folder | null => {
    const row = record(value);
    if (!row) return null;
    const attributes = record(row.attributes) || row;
    const id = attributes.id;
    const folderLabel = attributes['name'];
    if (typeof id !== 'number' || !isFinite(id) || id < 1) return null;
    if (typeof folderLabel !== 'string') return null;
    const rawServers = Array.isArray(attributes.servers) ? attributes.servers : [];
    const servers: string[] = [];
    for (let i = 0; i < rawServers.length; i++) {
        const entry = rawServers[i];
        if (typeof entry === 'string' && UUID.test(entry)) servers.push(entry);
    }
    const position = typeof attributes.position === 'number' && isFinite(attributes.position) ? attributes.position : 0;
    return { id: Math.round(id), name: folderLabel, position: Math.round(position), servers: servers };
};

export const parseFolderList = (body: unknown): Folder[] => {
    const row = record(body);
    const raw = row && Array.isArray(row.data) ? row.data : Array.isArray(body) ? body : [];
    const folders: Folder[] = [];
    for (let i = 0; i < raw.length; i++) {
        const folder = parseFolder(raw[i]);
        if (folder) folders.push(folder);
    }
    folders.sort((left, right) => left.position - right.position || left.id - right.id);
    return folders;
};

const oneFolder = (body: unknown): Folder => {
    const folder = parseFolder(body);
    if (!folder) throw new Error('bad-shape');
    return folder;
};

export interface BootstrapBits {
    folders: Folder[];
    canLayout: boolean;
    layout: LayoutId | null;
}

export const loadBootstrapBits = (): Promise<BootstrapBits> => {
    return http.get(BASE + '/bootstrap').then((response) => {
        const root = record(response.data);
        const attributes = root ? record(root.attributes) : null;
        const permissions = attributes ? record(attributes.permissions) : null;
        const design = attributes ? record(attributes.design) : null;
        const layoutValue = design && typeof design.server_layout === 'string' ? design.server_layout : null;
        const layout = layoutValue === 'raster' || layoutValue === 'liste' || layoutValue === 'kompakt' ? layoutValue : null;
        return {
            folders: attributes && Array.isArray(attributes.folders) ? parseFolderList(attributes.folders) : [],
            canLayout: !!(permissions && permissions.layout === true),
            layout: layout,
        };
    });
};

const namedPayload = (folderLabel: string): { [key: string]: string } => {
    const payload: { [key: string]: string } = {};
    payload['name'] = folderLabel.trim();
    return payload;
};

export const createFolder = (folderLabel: string): Promise<Folder> => {
    return http.post(BASE + '/folders', namedPayload(folderLabel)).then((response) => oneFolder(response.data));
};

export const renameFolder = (id: number, folderLabel: string): Promise<Folder> => {
    return http.patch(BASE + '/folders/' + id, namedPayload(folderLabel)).then((response) => oneFolder(response.data));
};

export const removeFolder = (id: number): Promise<void> => {
    return http.delete(BASE + '/folders/' + id).then(() => undefined);
};

export const orderFolders = (ids: number[]): Promise<Folder[]> => {
    return http.put(BASE + '/folders/order', { folders: ids }).then((response) => {
        const folders = parseFolderList(response.data);
        if (folders.length !== ids.length) throw new Error('bad-shape');
        return folders;
    });
};

export const putServers = (id: number, servers: string[]): Promise<Folder> => {
    return http.put(BASE + '/folders/' + id + '/servers', { servers: servers }).then((response) => oneFolder(response.data));
};

// Full replacement. The API moves the server out of every other folder.
export const placeServer = (folders: Folder[], serverId: string, target: number | null): Promise<Folder[]> => {
    let current: Folder | null = null;
    for (let i = 0; i < folders.length; i++) {
        const folder = folders[i];
        for (let s = 0; s < folder.servers.length; s++) {
            if (sameId(folder.servers[s], serverId)) current = folder;
        }
    }
    if (target === null) {
        if (!current) return Promise.resolve(folders);
        const nextServers = current.servers.filter((id) => !sameId(id, serverId));
        return putServers(current.id, nextServers).then((updated) =>
            folders.map((folder) => (folder.id === updated.id ? updated : folder))
        );
    }
    if (current && current.id === target) return Promise.resolve(folders);
    let destination: Folder | null = null;
    for (let i = 0; i < folders.length; i++) {
        if (folders[i].id === target) destination = folders[i];
    }
    if (!destination) return Promise.resolve(folders);
    const nextServers = destination.servers.slice();
    let already = false;
    for (let i = 0; i < nextServers.length; i++) {
        if (sameId(nextServers[i], serverId)) already = true;
    }
    if (!already) nextServers.push(serverId);
    return putServers(target, nextServers).then((updated) =>
        folders.map((folder) => {
            if (folder.id === updated.id) return updated;
            return { ...folder, servers: folder.servers.filter((id) => !sameId(id, serverId)) };
        })
    );
};

export const saveLayout = (layout: LayoutId): Promise<void> => {
    return http.patch(BASE + '/settings', { design: { server_layout: layout } }).then(() => undefined);
};

export const readPage = (): number => {
    const value = Number(new URLSearchParams(window.location.search).get('page') || '1');
    return value > 0 && isFinite(value) ? Math.round(value) : 1;
};

// The panel's "show all servers" switch. Only root admins have it.
export const readAdminList = (): boolean => {
    const input = document.querySelector('input[name="show_all_servers"]');
    return input instanceof HTMLInputElement && input.checked;
};

const eggLabel = (source: { [key: string]: unknown } | null): string => {
    if (!source) return '';
    const relationships = record(source.relationships);
    if (!relationships) return '';
    const egg = record(relationships.egg);
    if (!egg) return '';
    const attributes = record(egg.attributes);
    if (attributes && typeof attributes['name'] === 'string') return attributes['name'];
    if (typeof egg['name'] === 'string') return egg['name'];
    return '';
};

const readFact = (item: unknown): { id: string; fact: ServerFacts } | null => {
    const row = record(item);
    if (!row) return null;
    const attributes = record(row.attributes);
    if (!attributes || typeof attributes.uuid !== 'string' || !UUID.test(attributes.uuid)) return null;
    return {
        id: attributes.uuid.toLowerCase(),
        fact: {
            owner: attributes.server_owner === true,
            egg: eggLabel(attributes) || eggLabel(row),
        },
    };
};

// Same page the dashboard asked for. include=egg is optional on the client API
// and is the only place the egg name is exposed; the card's own object drops it.
export const fetchFacts = (page: number, admin: boolean): Promise<{ [id: string]: ServerFacts }> => {
    const params: { include: string; per_page: number; page: number; type?: string } = {
        include: 'egg',
        per_page: 50,
        page: page,
    };
    if (admin) params.type = 'admin';
    return http.get('/api/client', { params: params }).then((response) => {
        const out: { [id: string]: ServerFacts } = {};
        const data = record(response.data);
        const rows = data && Array.isArray(data.data) ? data.data : [];
        for (let i = 0; i < rows.length; i++) {
            const fact = readFact(rows[i]);
            if (fact) out[fact.id] = fact.fact;
        }
        return out;
    });
};

export const sendPower = (id: string, signal: Signal): Promise<void> => {
    return http.post('/api/client/servers/' + id + '/power', { signal: signal }).then(() => undefined);
};

// At most three requests at once. Failures are returned, never swallowed.
export const runPower = (
    items: { id: string; label: string }[],
    signal: Signal,
    onProgress: (done: number, total: number) => void
): Promise<{ ok: number; failed: PowerFailure[] }> => {
    const total = items.length;
    let cursor = 0;
    let done = 0;
    let ok = 0;
    const failed: PowerFailure[] = [];

    const worker = (): Promise<void> => {
        const step = (): Promise<void> => {
            if (cursor >= total) return Promise.resolve();
            const index = cursor;
            cursor += 1;
            const item = items[index];
            return sendPower(item.id, signal)
                .then(() => {
                    ok += 1;
                })
                .catch((error: unknown) => {
                    failed.push({ id: item.id, label: item.label, detail: errorText(error, '') });
                })
                .then(() => {
                    done += 1;
                    onProgress(done, total);
                    return step();
                });
        };
        return step();
    };

    const slots = Math.min(3, total);
    const workers: Promise<void>[] = [];
    for (let i = 0; i < slots; i++) workers.push(worker());
    return Promise.all(workers).then(() => ({ ok: ok, failed: failed }));
};
