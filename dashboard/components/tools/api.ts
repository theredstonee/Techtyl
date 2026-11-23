import http, { httpErrorToHuman } from '@/api/http';
import { tt } from './text';

/*
 * Client der Server-Werkzeuge (/api/client/extensions/techtyl/tools/servers/<uuid>/...).
 * Weitere Seiten (Spieler, Welten, Eigenschaften, Logs) nutzen getMeta(), die Vorgaenge und
 * request() fuer eigene Endpunkte.
 */

const base = (server: string) => `/api/client/extensions/techtyl/tools/servers/${server}`;

/** GET/POST auf einen Endpunkt der Werkzeuge; liefert die "attributes" der Antwort. */
export const request = async <T>(server: string, method: 'get' | 'post', path: string, data?: Record<string, unknown>): Promise<T> => {
    const response =
        method === 'get'
            ? await http.get(base(server) + path, { params: data })
            : await http.post(base(server) + path, data || {});

    return (response.data ? response.data.attributes : undefined) as T;
};

/** Fehlermeldung fuer Menschen; ohne Antwort des Servers ein Hinweis auf die Verbindung. */
export const explain = (error: unknown, fallback?: string): string => {
    const anyError = error as { response?: { status?: number; data?: unknown } };
    if (!anyError || !anyError.response) {
        return tt('no_connection');
    }
    if (anyError.response.status === 403) {
        const text = httpErrorToHuman(error);

        return text && !/^This action is unauthorized/i.test(text) && !/unauthorized|forbidden/i.test(text)
            ? text
            : tt('no_right');
    }
    if (anyError.response.status === 429) {
        const text = httpErrorToHuman(error);

        return /seconds|Sekunden/.test(text) ? text : tt('too_many');
    }
    const text = httpErrorToHuman(error);

    return text && text.length < 400 ? text : fallback || tt('generic_error');
};

export const statusOf = (error: unknown): number => {
    const anyError = error as { response?: { status?: number } };

    return (anyError && anyError.response && anyError.response.status) || 0;
};

/* ------------------------------------------------------------------ Meta */

export type ToolKey = 'content' | 'versions' | 'players' | 'worlds' | 'properties' | 'logs';

export interface ToolsMeta {
    type: string;
    type_label: string;
    type_source: 'admin' | 'auto';
    minecraft: boolean;
    egg: string;
    version: string | null;
    build: string | null;
    version_source: string | null;
    loader: string | null;
    loader_version: string | null;
    tools: Record<ToolKey, { available: boolean; planned?: boolean; kind?: 'plugin' | 'mod'; directory?: string; loaders?: string[] }>;
    permissions: Record<string, boolean>;
}

export const getMeta = (server: string) => request<ToolsMeta>(server, 'get', '/meta');

/* ------------------------------------------------------------------ Vorgaenge */

export interface ToolsTask {
    uuid: string;
    kind: string;
    title: string;
    status: 'pending' | 'running' | 'done' | 'failed';
    step: string;
    step_label: string;
    progress: number;
    error: string | null;
    subject: Record<string, any>;
    result: Record<string, any> | null;
    created_at: string;
    finished_at: string | null;
}

export interface TaskOverview {
    active: ToolsTask[];
    recent: ToolsTask[];
}

export const getTasks = (server: string) => request<TaskOverview>(server, 'get', '/tasks');
export const getTask = (server: string, uuid: string) => request<ToolsTask>(server, 'get', `/tasks/${uuid}`);

/* ------------------------------------------------------------------ Plugins und Mods */

export interface ContentFilters {
    kind: 'plugin' | 'mod';
    directory: string;
    loaders: string[];
    type_label: string;
    game_version: string | null;
    categories: { id: string; label: string }[];
    sorts: { id: string; label: string }[];
}

export interface ContentHit {
    project_id: string;
    slug: string;
    title: string;
    description: string;
    author: string;
    icon_url: string | null;
    downloads: number;
    follows: number;
    categories: string[];
    updated_at: string;
    installed: boolean;
}

export interface ContentSearch {
    hits: ContentHit[];
    total: number;
    page: number;
    pages: number;
    game_version: string | null;
}

export interface ContentProject {
    project_id: string;
    slug: string;
    title: string;
    description: string;
    icon_url: string | null;
    downloads: number;
    follows: number;
    categories: string[];
    loaders: string[];
    body: string;
    links: { label: string; url: string }[];
    gallery: { url: string; title: string }[];
    license: string;
    updated_at: string;
    page_url: string;
    installed_version_id: string | null;
}

export interface ContentVersion {
    id: string;
    title: string;
    number: string;
    channel: 'release' | 'beta' | 'alpha';
    loaders: string[];
    game_versions: string[];
    game_version_count: number;
    matches_server: boolean | null;
    published_at: string;
    downloads: number;
    filename: string;
    size: number;
    installable: boolean;
    required_count: number;
}

export interface ContentVersions {
    game_version: string | null;
    filtered: boolean;
    installed_version_id: string | null;
    versions: ContentVersion[];
}

export interface ContentDependency {
    project_id: string;
    title: string;
    icon_url: string | null;
    description: string;
    installed: boolean;
    version: ContentVersion | null;
}

export interface InstalledItem {
    filename: string;
    size: number;
    modified_at: string;
    state: 'tracked' | 'changed' | 'unknown';
    project_id: string | null;
    title: string | null;
    icon_url: string | null;
    version_id: string | null;
    version_number: string | null;
    installed_at: string | null;
}

export interface ContentUpdates {
    checked: boolean;
    reason: string | null;
    updates: Record<string, { version_id: string; version_number: string; channel: string }>;
}

export interface SearchQuery {
    query: string;
    sort: string;
    page: number;
    categories: string[];
    anyVersion: boolean;
}

export const getFilters = (server: string) => request<ContentFilters>(server, 'get', '/content/filters');

export const searchContent = (server: string, q: SearchQuery) =>
    request<ContentSearch>(server, 'get', '/content/search', {
        query: q.query,
        sort: q.sort,
        page: q.page,
        categories: q.categories,
        any_version: q.anyVersion ? 1 : 0,
    });

export const getProject = (server: string, project: string) => request<ContentProject>(server, 'get', `/content/projects/${project}`);

export const getProjectVersions = (server: string, project: string, all: boolean) =>
    request<ContentVersions>(server, 'get', `/content/projects/${project}/versions`, { all: all ? 1 : 0 });

export const getDependencies = (server: string, versionId: string) =>
    request<{ dependencies: ContentDependency[] }>(server, 'get', `/content/versions/${versionId}/dependencies`).then((data) => data.dependencies);

export const getInstalled = (server: string) =>
    request<{ kind: string; directory: string; items: InstalledItem[] }>(server, 'get', '/content/installed');

export const getUpdates = (server: string) => request<ContentUpdates>(server, 'get', '/content/updates');

export const installContent = (server: string, projectId: string, versionId: string) =>
    request<ToolsTask>(server, 'post', '/content/install', { project_id: projectId, version_id: versionId });

export const updateContent = (server: string, file: string) => request<ToolsTask>(server, 'post', '/content/update', { file });

export const removeContent = (server: string, file: string) => request<void>(server, 'post', '/content/remove', { file });

/* ------------------------------------------------------------------ Versionen */

export interface VersionEntry {
    id: string;
    stable: boolean;
    group: string;
}

export interface VersionList {
    type: string;
    type_label: string;
    planned: boolean;
    build_label: string;
    has_builds: boolean;
    proxy: boolean;
    current: { version: string | null; build: string | null; source: string | null };
    jar: string;
    versions: VersionEntry[];
    switching: boolean;
}

export interface BuildEntry {
    id: string;
    label: string;
    channel: string;
    time: string | null;
    size: number | null;
}

export interface VersionPreview {
    type: string;
    from: { version: string | null; build: string | null };
    to: { version: string; build: string };
    direction: 'upgrade' | 'downgrade' | 'build' | 'same' | 'unknown';
    jar: string;
    jar_backup: string;
    download_size: number | null;
    java: { required: number; current_image: string; new_image: string | null };
    variables: { key: string; label: string; from: string | null; to: string; set: boolean; reason: string | null }[];
    server_state: string;
    can_stop: boolean;
    can_start: boolean;
    backup: { possible: boolean; reason: string | null; used: number; limit: number };
    warnings: { level: 'info' | 'warning' | 'danger'; text: string }[];
}

export const getVersions = (server: string) => request<VersionList>(server, 'get', '/versions');

export const getBuilds = (server: string, ver: string) =>
    request<{ builds: BuildEntry[] }>(server, 'get', '/versions/builds', { version: ver }).then((data) => data.builds);

export const previewSwitch = (server: string, ver: string, build: string) =>
    request<VersionPreview>(server, 'post', '/versions/preview', { version: ver, build });

export const switchVersion = (server: string, ver: string, build: string, backup: boolean, start: boolean) =>
    request<ToolsTask>(server, 'post', '/versions/switch', { version: ver, build, backup, start });

/** Server ueber die Panel-API schalten (start, stop, restart). */
export const sendPower = (server: string, signal: 'start' | 'stop' | 'restart') =>
    http.post(`/api/client/servers/${server}/power`, { signal });

/* ------------------------------------------------------------------ Eigenschaften */

export interface PropertyField {
    key: string;
    type: 'bool' | 'int' | 'enum' | 'text' | string;
    group: string;
    value: string;
    default: string;
    present: boolean;
    hint: string;
    min: number | null;
    max: number | null;
    options: string[] | null;
    secret: boolean;
    custom: boolean;
}

export interface PropertiesView {
    exists: boolean;
    bytes: number;
    odd: number;
    fields: PropertyField[];
}

export interface PropertiesSave {
    saved: boolean;
    restart: boolean;
    changed: number;
    created: boolean;
}

export const getProperties = (server: string) => request<PropertiesView>(server, 'get', '/properties');

export const saveProperties = (server: string, changes: Record<string, string>, create: boolean) =>
    request<PropertiesSave>(server, 'post', '/properties', { changes, create });

/* ------------------------------------------------------------------ Logs */

export interface LogView {
    file: string;
    missing: boolean;
    too_large: boolean;
    truncated: boolean;
    bytes: number;
    text: string;
    sharing: boolean;
    name?: string;
}

export interface CrashReport {
    name: string;
    size: number;
    modified: string;
}

export interface LogShare {
    id: string;
    url: string;
}

export const getLog = (server: string) => request<LogView>(server, 'get', '/logs');

export const getCrashes = (server: string) => request<{ reports: CrashReport[] }>(server, 'get', '/logs/crashes');

export const getCrash = (server: string, file: string) => request<LogView>(server, 'get', `/logs/crashes/${encodeURIComponent(file)}`);

export const shareLog = (server: string, content: string) => request<LogShare>(server, 'post', '/logs/share', { content });

/* ------------------------------------------------------------------ Spieler */

export interface PlayerRow {
    name: string;
    uuid: string | null;
    reason: string;
    level: number | null;
    actionable: boolean;
}

export interface IpBanRow {
    ip: string;
    reason: string;
}

export interface PlayerList {
    missing: boolean;
    broken: boolean;
    too_large: boolean;
    entries: PlayerRow[];
}

export interface IpBanList {
    missing: boolean;
    broken: boolean;
    too_large: boolean;
    entries: IpBanRow[];
}

export interface PlayerQuery {
    status: string;
    online: number | null;
    max: number | null;
    sample: { name: string; uuid: string | null }[];
}

export interface PlayersView {
    state: string;
    online_mode: boolean;
    query: PlayerQuery;
    whitelist: PlayerList;
    ops: PlayerList;
    bans: PlayerList;
    ip_bans: IpBanList;
}

export type PlayerAction = 'whitelist_add' | 'whitelist_remove' | 'op' | 'deop' | 'ban' | 'pardon' | 'kick';

export const getPlayers = (server: string) => request<PlayersView>(server, 'get', '/players');

export const playerCommand = (server: string, action: PlayerAction, player: string, reason: string) =>
    request<{ ok: boolean; via: string }>(server, 'post', '/players/command', { action, player, reason });

export const playerFile = (server: string, action: PlayerAction, player: string, reason: string) =>
    request<{ ok: boolean; via: string }>(server, 'post', '/players/files', { action, player, reason });

/* ------------------------------------------------------------------ Welten */

export interface WorldRow {
    folder: string;
    role: 'overworld' | 'nether' | 'end' | 'other' | string;
    primary: boolean;
    size: number | null;
    modified: string;
}

export interface WorldsView {
    state: string;
    level_name: string | null;
    truncated: boolean;
    worlds: WorldRow[];
}

export interface WorldBackup {
    folder: string;
    archive: string;
    size: number;
}

export const getWorlds = (server: string) => request<WorldsView>(server, 'get', '/worlds');

export const backupWorld = (server: string, world: string, confirmRunning: boolean) =>
    request<WorldBackup>(server, 'post', '/worlds/backup', { world, confirm_running: confirmRunning });

export const deleteWorld = (server: string, world: string, confirm: string, confirmLevel: string) =>
    request<{ folder: string; deleted: boolean }>(server, 'post', '/worlds/delete', { world, confirm, confirm_level: confirmLevel });
