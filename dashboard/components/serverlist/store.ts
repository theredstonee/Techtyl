// Tiny store shared by the toolbar and the server cards. They are siblings in the
// panel tree, so React context from the toolbar cannot reach the cards.
import { useEffect, useState } from 'react';
import { Folder, ServerFacts } from './types';

export interface ListState {
    folders: Folder[];
    foldersReady: boolean;
    selecting: boolean;
    selected: string[];
    facts: { [id: string]: ServerFacts };
    factsReady: boolean;
    factsError: boolean;
}

const empty: ListState = {
    folders: [],
    foldersReady: false,
    selecting: false,
    selected: [],
    facts: {},
    factsReady: false,
    factsError: false,
};

let state: ListState = empty;
const listeners = new Set<() => void>();

const emit = (): void => {
    listeners.forEach((listener) => listener());
};

const replace = (next: ListState): void => {
    state = next;
    emit();
};

export const getListState = (): ListState => state;

export const subscribeList = (listener: () => void): (() => void) => {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
};

export const useListState = (): ListState => {
    const [snapshot, setSnapshot] = useState(state);
    useEffect(() => {
        const unsubscribe = subscribeList(() => setSnapshot(getListState()));
        setSnapshot(getListState());
        return unsubscribe;
    }, []);
    return snapshot;
};

export const hasId = (ids: string[], id: string): boolean => {
    const key = id.toLowerCase();
    for (let i = 0; i < ids.length; i++) {
        if (ids[i].toLowerCase() === key) return true;
    }
    return false;
};

export const setFolders = (folders: Folder[], ready: boolean): void => {
    replace({ ...state, folders: folders, foldersReady: ready });
};

export const setSelecting = (on: boolean): void => {
    replace({ ...state, selecting: on, selected: on ? state.selected : [] });
};

export const selectIds = (ids: string[]): void => {
    const next = state.selected.slice();
    for (let i = 0; i < ids.length; i++) {
        if (!hasId(next, ids[i])) next.push(ids[i]);
    }
    replace({ ...state, selected: next });
};

export const deselectIds = (ids: string[]): void => {
    const drop: { [key: string]: true } = {};
    for (let i = 0; i < ids.length; i++) drop[ids[i].toLowerCase()] = true;
    replace({ ...state, selected: state.selected.filter((id) => !drop[id.toLowerCase()]) });
};

export const toggleSelected = (id: string): void => {
    if (hasId(state.selected, id)) deselectIds([id]);
    else selectIds([id]);
};

export const clearSelected = (): void => {
    replace({ ...state, selected: [] });
};

export const pruneSelected = (present: string[]): void => {
    const allow: { [key: string]: true } = {};
    for (let i = 0; i < present.length; i++) allow[present[i].toLowerCase()] = true;
    const next = state.selected.filter((id) => allow[id.toLowerCase()]);
    if (next.length === state.selected.length) return;
    replace({ ...state, selected: next });
};

export const setFacts = (facts: { [id: string]: ServerFacts }, ready: boolean, failed: boolean): void => {
    replace({ ...state, facts: facts, factsReady: ready, factsError: failed });
};
