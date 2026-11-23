// Remembers search, filter chip and last folder for this browser tab only.
import { Chip, FolderPick } from './types';

const QUERY = 'techtyl.serverlist.query';
const CHIP = 'techtyl.serverlist.chip';
const FOLDER = 'techtyl.serverlist.folder';

const CHIPS: Chip[] = ['all', 'running', 'offline', 'mine', 'shared'];

const readStore = (key: string): string => {
    try {
        return sessionStorage.getItem(key) || '';
    } catch (error) {
        return '';
    }
};

const writeStore = (key: string, value: string): void => {
    try {
        sessionStorage.setItem(key, value);
    } catch (error) {
        // Private mode and blocked storage: the list still works, it just forgets.
    }
};

export const readQuery = (): string => readStore(QUERY).slice(0, 200);

export const writeQuery = (value: string): void => {
    writeStore(QUERY, value.slice(0, 200));
};

export const readChip = (): Chip => {
    const value = readStore(CHIP);
    for (let i = 0; i < CHIPS.length; i++) {
        if (CHIPS[i] === value) return CHIPS[i];
    }
    return 'all';
};

export const writeChip = (value: Chip): void => {
    writeStore(CHIP, value);
};

export const readFolder = (): FolderPick => {
    const value = readStore(FOLDER);
    if (value === 'none') return 'none';
    if (/^\d{1,10}$/.test(value)) {
        const id = Number(value);
        if (id > 0) return id;
    }
    return 'all';
};

export const writeFolder = (value: FolderPick): void => {
    writeStore(FOLDER, String(value));
};
