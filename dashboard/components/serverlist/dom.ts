// The panel renders the cards. We only show or hide them, and watch the list
// they live in. A class would be wiped the next time React sets className,
// so the hidden flag is a data attribute React does not own.
import { Chip, FolderPick } from './types';

export interface ListFilter {
    query: string;
    chip: Chip;
    folder: FolderPick;
    membership: { [id: string]: number };
    ownerReady: boolean;
}

export interface CardInfo {
    id: string;
    label: string;
    hidden: boolean;
}

export interface ListSnapshot {
    total: number;
    shown: number;
    loose: number;
    paged: boolean;
    ids: string[];
    visibleIds: string[];
    cards: CardInfo[];
}

export const emptySnapshot = (): ListSnapshot => ({
    total: 0,
    shown: 0,
    loose: 0,
    paged: false,
    ids: [],
    visibleIds: [],
    cards: [],
});

const wordsOf = (query: string): string[] => {
    const parts = query.toLowerCase().split(/\s+/);
    const words: string[] = [];
    for (let i = 0; i < parts.length; i++) {
        if (parts[i] !== '') words.push(parts[i]);
    }
    return words;
};

const matchesQuery = (card: HTMLElement, query: string): boolean => {
    const words = wordsOf(query);
    if (words.length === 0) return true;
    const blob = (card.getAttribute('data-techtyl-search') || '').toLowerCase();
    for (let i = 0; i < words.length; i++) {
        if (blob.indexOf(words[i]) === -1) return false;
    }
    return true;
};

const matchesChip = (card: HTMLElement, chip: Chip, ownerReady: boolean): boolean => {
    if (chip === 'all') return true;
    if (chip === 'running') return card.getAttribute('data-state') === 'running';
    if (chip === 'offline') return card.getAttribute('data-state') === 'offline';
    if (!ownerReady) return true;
    const owner = card.getAttribute('data-techtyl-owner');
    // A card we could not classify stays visible. Hiding it would look like an empty list.
    if (owner !== 'mine' && owner !== 'shared') return true;
    return owner === chip;
};

const matchesFolder = (id: string, folder: FolderPick, membership: { [id: string]: number }): boolean => {
    if (folder === 'all') return true;
    const home = membership[id.toLowerCase()];
    if (folder === 'none') return typeof home !== 'number';
    return home === folder;
};

const setHidden = (card: HTMLElement, hidden: boolean): void => {
    const current = card.getAttribute('data-techtyl-hidden') === '1';
    if (hidden && !current) card.setAttribute('data-techtyl-hidden', '1');
    if (!hidden && current) card.removeAttribute('data-techtyl-hidden');
};

const cardLabel = (card: HTMLElement, id: string): string => {
    const node = card.querySelector('.techtyl-card__name');
    const text = node && node.textContent ? node.textContent.trim() : '';
    return text || id;
};

const hasPager = (list: HTMLElement): boolean => {
    const nodes = list.children;
    for (let i = 0; i < nodes.length; i++) {
        const node = nodes[i];
        if (!(node instanceof HTMLElement)) continue;
        if (node.className && String(node.className).indexOf('Pagination') !== -1) return true;
    }
    return false;
};

export const applyListFilter = (list: HTMLElement, filter: ListFilter): ListSnapshot => {
    const nodes = list.querySelectorAll(':scope > .techtyl-card');
    const ids: string[] = [];
    const visibleIds: string[] = [];
    const cards: CardInfo[] = [];
    let loose = 0;

    for (let i = 0; i < nodes.length; i++) {
        const card = nodes[i];
        if (!(card instanceof HTMLElement)) continue;
        const id = card.getAttribute('data-techtyl-server-id') || '';
        if (!id) continue;
        const looseCard = typeof filter.membership[id.toLowerCase()] !== 'number';
        if (looseCard) loose += 1;
        const hidden = !(
            matchesQuery(card, filter.query) &&
            matchesChip(card, filter.chip, filter.ownerReady) &&
            matchesFolder(id, filter.folder, filter.membership)
        );
        setHidden(card, hidden);
        ids.push(id);
        if (!hidden) visibleIds.push(id);
        cards.push({ id: id, label: cardLabel(card, id), hidden: hidden });
    }

    return {
        total: ids.length,
        shown: visibleIds.length,
        loose: loose,
        paged: hasPager(list),
        ids: ids,
        visibleIds: visibleIds,
        cards: cards,
    };
};

// One observer on the list container. Attribute changes we make ourselves
// (data-techtyl-hidden) are not in the filter, so this does not loop.
export const watchServerList = (list: HTMLElement, onChange: () => void): (() => void) => {
    let frame = 0;
    const schedule = (): void => {
        window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(onChange);
    };
    const observer = new MutationObserver(schedule);
    observer.observe(list, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-state', 'data-techtyl-owner', 'data-techtyl-search', 'data-techtyl-server-id'],
    });
    return () => {
        window.cancelAnimationFrame(frame);
        observer.disconnect();
    };
};

export const searchBlob = (parts: Array<string | null | undefined>): string => {
    let out = '';
    for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        if (!part) continue;
        out += (out ? ' ' : '') + part;
    }
    return out.replace(/\s+/g, ' ').trim().toLowerCase();
};

// Egg name when the directory has it, otherwise the image tag (and features
// only when the egg name never arrived).
export const gameText = (egg: string, features: string[], image: string): string => {
    const bits: string[] = [];
    if (egg) {
        bits.push(egg);
    } else {
        for (let i = 0; i < features.length; i++) bits.push(features[i]);
    }
    const slash = image.lastIndexOf('/');
    const tail = (slash >= 0 ? image.slice(slash + 1) : image).replace(/[_:-]+/g, ' ');
    if (tail) bits.push(tail);
    return bits.join(' ');
};

export const percentOf = (done: number, total: number): number => {
    if (total <= 0) return 0;
    return Math.round((done / total) * 100);
};
