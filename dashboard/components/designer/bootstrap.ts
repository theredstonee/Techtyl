import http from '@/api/http';
import { isHttpUrl } from './markdown';

// Data from GET /api/client/extensions/techtyl/bootstrap that the designer parts need.
// Loaded once per page load and shared; refreshBootstrap() forces a new request.

export const VARIANTS = ['werft', 'papier', 'hafen', 'kiefer'] as const;
export const NAVIGATION = ['sidebar', 'icons', 'top'] as const;
export const SERVER_LAYOUTS = ['raster', 'liste', 'kompakt'] as const;
export const MOTION = ['aus', 'dezent', 'voll'] as const;
export const KINDS = ['info', 'neu', 'erfolg', 'warnung', 'wartung'] as const;
export const ICONS = ['megafon', 'info', 'stern', 'rakete', 'werkzeug', 'warnung', 'herz', 'kalender', 'glocke', 'geschenk'] as const;
export const WEBSITE_KINDS = ['support', 'status', 'billing', 'docs', 'website'] as const;
export const SOCIAL = ['discord', 'github', 'youtube', 'twitch', 'x', 'instagram', 'tiktok', 'mastodon', 'reddit'] as const;

export interface Design {
    variant: string;
    accent: string;
    radius: number;
    navigation: string;
    server_layout: string;
    motion: string;
    banners: boolean;
    banner_height: number;
    background: string;
    dim: number;
    opacity: number;
    blur: number;
    motif: string;
    login_title: string;
    login_subtitle: string;
    login_image: string;
}

export interface Announcement {
    text: string;
    kind: string;
    icon: string;
    position: string;
    dismissible: boolean;
    revision: string;
}

export interface LinkSet {
    website: { kind: string; label: string; url: string }[];
    social: { network: string; url: string }[];
    position: string;
    align: string;
}

export interface Motif {
    key: string;
    name: string;
    url: string;
    variant: string | null;
}

export interface BannerInfo {
    identifier: string;
    url: string;
    source: 'server' | 'egg' | 'default' | null;
    can_edit: boolean;
}

export interface Permissions {
    appearance: boolean;
    layout: boolean;
    background: boolean;
    banners: boolean;
}

export interface Bootstrap {
    design: Design;
    adminDesign: Design;
    userDesign: Partial<Design>;
    background: { url: string; custom: boolean; custom_url: string };
    permissions: Permissions;
    maxUploadBytes: number;
    mimeTypes: string[];
    banners: Record<string, BannerInfo>;
    bannerPosition: string;
    announcement: Announcement | null;
    links: LinkSet | null;
    motifs: Motif[];
}

const oneOf = <T extends string>(value: unknown, list: readonly T[]): T | null =>
    typeof value === 'string' && (list as readonly string[]).includes(value) ? (value as T) : null;

const cleanText = (value: unknown, max: number, multiline: boolean): string => {
    if (typeof value !== 'string') return '';
    let out = '';
    for (let i = 0; i < value.length && out.length < max; i++) {
        const code = value.charCodeAt(i);
        const control =
            code < 32 ||
            (code >= 0x7f && code <= 0x9f) ||
            (code >= 0x200b && code <= 0x200f) ||
            (code >= 0x2028 && code <= 0x202e) ||
            (code >= 0x2060 && code <= 0x206f) ||
            code === 0xfeff;
        out += control && !(multiline && code === 10) ? ' ' : value.charAt(i);
    }
    return out;
};

// Same checks as on the server, applied again to anything that reaches the dashboard
// (bootstrap data and the designer preview event).
export const cleanAnnouncement = (value: any): Announcement | null => {
    if (!value || typeof value !== 'object') return null;
    const text = cleanText(value.text, 600, true);
    if (!text.trim()) return null;
    return {
        text,
        kind: oneOf(value.kind, KINDS) || 'info',
        icon: oneOf(value.icon, ICONS) || 'megafon',
        position: oneOf(value.position, ['karte', 'leiste', 'schwebend']) || 'karte',
        dismissible: value.dismissible !== false,
        revision: typeof value.revision === 'string' ? value.revision.slice(0, 32) : '',
    };
};

export const cleanLinks = (value: any): LinkSet | null => {
    if (!value || typeof value !== 'object') return null;
    const website = (Array.isArray(value.website) ? value.website : [])
        .slice(0, 6)
        .filter((entry: any) => entry && oneOf(entry.kind, WEBSITE_KINDS) && isHttpUrl(entry.url))
        .map((entry: any) => ({ kind: entry.kind, label: cleanText(entry.label, 32, false).trim(), url: entry.url.trim() }));
    const social = (Array.isArray(value.social) ? value.social : [])
        .slice(0, 8)
        .filter((entry: any) => entry && oneOf(entry.network, SOCIAL) && isHttpUrl(entry.url))
        .map((entry: any) => ({ network: entry.network, url: entry.url.trim() }));
    if (!website.length && !social.length) return null;
    return {
        website,
        social,
        position: oneOf(value.position, ['oben', 'schwebend']) || 'oben',
        align: oneOf(value.align, ['links', 'mitte', 'rechts']) || 'rechts',
    };
};

// Image addresses for CSS: own panel paths or https, without characters that break out of url("...").
export const imageValue = (url: unknown): string | null => {
    if (typeof url !== 'string' || url === '' || url.length > 500) return null;
    if (/["'()\\<>\s]/.test(url)) return null;
    const local = url.charAt(0) === '/' && url.charAt(1) !== '/';
    return local || url.indexOf('https://') === 0 ? url : null;
};

const toBootstrap = (attributes: any): Bootstrap => {
    const limits = attributes.limits || {};
    const position = typeof attributes.banner_position === 'string' && /^\d{1,3}% \d{1,3}%$/.test(attributes.banner_position) ? attributes.banner_position : '50% 30%';
    return {
        design: attributes.design,
        adminDesign: attributes.admin_design || attributes.design,
        userDesign: attributes.user_design || {},
        background: attributes.background || { url: '', custom: false, custom_url: '' },
        permissions: attributes.permissions || { appearance: false, layout: false, background: false, banners: false },
        maxUploadBytes: Number(limits.max_upload_bytes) || 2097152,
        mimeTypes: Array.isArray(limits.mime_types) ? limits.mime_types : ['image/png', 'image/jpeg', 'image/webp'],
        banners: attributes.banners && typeof attributes.banners === 'object' ? attributes.banners : {},
        bannerPosition: position,
        announcement: cleanAnnouncement(attributes.announcement),
        links: cleanLinks(attributes.links),
        motifs: Array.isArray(attributes.motifs)
            ? attributes.motifs.filter((motif: any) => motif && typeof motif.key === 'string' && imageValue(motif.url))
            : [],
    };
};

let pending: Promise<Bootstrap> | null = null;

export const loadBootstrap = (): Promise<Bootstrap> => {
    if (!pending) {
        pending = http
            .get('/api/client/extensions/techtyl/bootstrap')
            .then((response) => toBootstrap((response.data && response.data.attributes) || {}))
            .catch((error) => {
                pending = null;
                throw error;
            });
    }
    return pending;
};

export const refreshBootstrap = (): Promise<Bootstrap> => {
    pending = null;
    return loadBootstrap();
};
