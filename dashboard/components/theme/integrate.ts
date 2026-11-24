import { Locale } from '@blueprint/extensions/techtyl/i18n';
import { icons, resolveIcon } from './icons';
import { accountPages, fallbackIcon, NamedRoute, pageLabel, serverPages, ServerGroup } from './labels';

// Client-side copy of the server rules for techtyl.json and the bootstrap
// payload. Anything that does not match is dropped. Text is plain: the
// navigation renders it as text, never as HTML.

export type IntegrationGroup = 'manage' | 'configure' | 'tools' | 'extensions';

export interface IntegrationEntry {
    group: IntegrationGroup;
    icon?: string;
    label: { en?: string; de?: string };
    order: number;
    extension: string;
}

export interface Integrations {
    server: Record<string, IntegrationEntry>;
    account: Record<string, IntegrationEntry>;
}

export interface NavHidden {
    extensions: string[];
    pages: string[];
}

export interface Decorated {
    group: ServerGroup;
    icon: ReturnType<typeof resolveIcon>;
    label: string;
    order: number;
    keywords: string;
}

type Translate = (key: string, replace?: Record<string, string | number>) => string;

const PATH = /^\/[A-Za-z0-9/_-]{0,64}$/;
const EXTENSION_ID = /^[a-z0-9][a-z0-9_-]{0,63}$/;
const PAGE_KEY = /^(server|account):([a-z0-9][a-z0-9_-]{0,63}):(\/[A-Za-z0-9/_-]{0,64})$/;
const GROUPS: IntegrationGroup[] = ['manage', 'configure', 'tools', 'extensions'];
const OURS = 'techtyl';

export const EMPTY_INTEGRATIONS: Integrations = { server: {}, account: {} };
export const EMPTY_HIDDEN: NavHidden = { extensions: [], pages: [] };

const plainText = (value: unknown): string => {
    if (typeof value !== 'string') return '';
    const kept: string[] = [];
    const chars = Array.from(value);
    for (let i = 0; i < chars.length && kept.length < 40; i++) {
        const ch = chars[i];
        const code = ch.codePointAt(0) || 0;
        if (code < 32 || code === 127) continue;
        if (code >= 0x200b && code <= 0x200f) continue;
        if (code >= 0x202a && code <= 0x202e) continue;
        if (code >= 0x2066 && code <= 0x2069) continue;
        if (code === 0xfeff) continue;
        if (ch === '<' || ch === '>') continue;
        kept.push(ch);
    }

    return kept.join('').trim();
};

const cleanLabel = (value: unknown): { en?: string; de?: string } => {
    const label: { en?: string; de?: string } = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return label;
    const source = value as Record<string, unknown>;
    (['en', 'de'] as const).forEach((code) => {
        const text = plainText(source[code]);
        if (text !== '') label[code] = text;
    });

    return label;
};

const cleanSide = (value: unknown, withGroup: boolean): Record<string, IntegrationEntry> => {
    const out: Record<string, IntegrationEntry> = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return out;
    // The scan keeps at most 200 files with 40 entries each.
    Object.keys(value)
        .slice(0, 8000)
        .forEach((path) => {
            if (!PATH.test(path) || Object.prototype.hasOwnProperty.call(out, path)) return;
            const raw = (value as Record<string, unknown>)[path];
            if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return;
            const source = raw as Record<string, unknown>;
            const extension = source.extension;
            if (typeof extension !== 'string' || !EXTENSION_ID.test(extension) || extension === OURS) return;
            let order = 100;
            if (typeof source.order === 'number' && isFinite(source.order)) {
                const rounded = Math.round(source.order);
                if (rounded >= -10000 && rounded <= 10000) order = rounded;
            }
            const entry: IntegrationEntry = {
                group: 'extensions',
                label: cleanLabel(source.label),
                order,
                extension,
            };
            if (withGroup && typeof source.group === 'string' && GROUPS.indexOf(source.group as IntegrationGroup) >= 0) {
                entry.group = source.group as IntegrationGroup;
            }
            if (typeof source.icon === 'string' && Object.prototype.hasOwnProperty.call(icons, source.icon)) {
                entry.icon = source.icon;
            }
            out[path] = entry;
        });

    return out;
};

export const cleanIntegrations = (value: unknown): Integrations => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return EMPTY_INTEGRATIONS;
    const source = value as Record<string, unknown>;

    return {
        server: cleanSide(source.server, true),
        account: cleanSide(source.account, false),
    };
};

export const cleanHidden = (value: unknown): NavHidden => {
    const extensions: string[] = [];
    const pages: string[] = [];
    if (!value || typeof value !== 'object' || Array.isArray(value)) return { extensions, pages };
    const source = value as Record<string, unknown>;
    const rawExtensions = Array.isArray(source.extensions) ? source.extensions : [];
    const rawPages = Array.isArray(source.pages) ? source.pages : [];
    rawExtensions.slice(0, 200).forEach((item) => {
        if (typeof item === 'string' && EXTENSION_ID.test(item) && item !== OURS && item !== 'blueprint') {
            extensions.push(item);
        }
    });
    rawPages.slice(0, 500).forEach((item) => {
        if (typeof item !== 'string' || item.length > 180) return;
        const match = PAGE_KEY.exec(item);
        if (!match || match[2] === OURS || match[2] === 'blueprint') return;
        pages.push(item);
    });

    return { extensions, pages };
};

// A route another extension added. Panel routes have no identifier.
// Techtyl routes keep the groups declared in labels.ts.
export const isForeign = (route: NamedRoute): boolean => {
    const id = route.identifier || '';

    return id !== '' && id !== OURS;
};

export const isHidden = (side: 'server' | 'account', route: NamedRoute, hidden: NavHidden): boolean => {
    if (!isForeign(route)) return false;
    const id = route.identifier || '';
    if (hidden.extensions.indexOf(id) >= 0) return true;

    return hidden.pages.indexOf(side + ':' + id + ':' + route.path) >= 0;
};

const entryFor = (map: Record<string, IntegrationEntry>, route: NamedRoute): IntegrationEntry | undefined => {
    const entry = map[route.path];
    if (!entry || entry.extension !== route.identifier) return undefined;

    return entry;
};

const entryLabel = (entry: IntegrationEntry | undefined, locale: Locale, fallback: string): string => {
    if (!entry) return fallback;
    const text = (locale === 'de' && entry.label.de) || entry.label.en || '';

    return text || fallback;
};

const keywordsFor = (route: NamedRoute, entry: IntegrationEntry | undefined): string => {
    const bits = [route.name || '', route.path];
    if (entry && entry.label.en) bits.push(entry.label.en);
    if (entry && entry.label.de) bits.push(entry.label.de);

    return bits.join(' ');
};

export const decorateServer = (route: NamedRoute, integrations: Integrations, locale: Locale, t: Translate): Decorated => {
    const foreign = isForeign(route);
    const entry = foreign ? entryFor(integrations.server, route) : undefined;
    const known = serverPages[route.path];

    return {
        group: foreign ? (entry ? entry.group : 'extensions') : known ? known.group : 'tools',
        icon: foreign ? resolveIcon(entry && entry.icon) : known ? known.icon : fallbackIcon,
        label: foreign ? entryLabel(entry, locale, route.name || route.path) : pageLabel(t, 's', route),
        order: entry ? entry.order : 100,
        keywords: keywordsFor(route, entry),
    };
};

export const decorateAccount = (route: NamedRoute, integrations: Integrations, locale: Locale, t: Translate): Decorated => {
    const foreign = isForeign(route);
    const entry = foreign ? entryFor(integrations.account, route) : undefined;
    const known = accountPages[route.path];

    return {
        // Own account links are not grouped. Extensions is the only account group.
        group: foreign ? 'extensions' : 'overview',
        icon: foreign ? resolveIcon(entry && entry.icon) : known ? known.icon : fallbackIcon,
        label: foreign ? entryLabel(entry, locale, route.name || route.path) : pageLabel(t, 'a', route),
        order: entry ? entry.order : 100,
        keywords: keywordsFor(route, entry),
    };
};
