// Shared shapes for the server list. Folder names are plain text from the API.

export type Chip = 'all' | 'running' | 'offline' | 'mine' | 'shared';
export type FolderPick = 'all' | 'none' | number;
export type LayoutId = 'raster' | 'liste' | 'kompakt';
export type Signal = 'start' | 'restart' | 'stop';
export type NameIssue = 'empty' | 'long' | 'chars';

export interface Folder {
    id: number;
    name: string;
    position: number;
    servers: string[];
}

export interface ServerFacts {
    owner: boolean;
    egg: string;
}

export interface ListedServer {
    id: string;
    label: string;
}

export interface PowerFailure {
    id: string;
    label: string;
    detail: string;
}

export interface PowerReport {
    signal: Signal;
    ok: number;
    failed: PowerFailure[];
}

export const MAX_FOLDERS = 50;
export const LAYOUTS: LayoutId[] = ['raster', 'liste', 'kompakt'];

export const isLayout = (value: string | null): value is LayoutId => {
    return value === 'raster' || value === 'liste' || value === 'kompakt';
};
