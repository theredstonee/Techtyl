import http from '@/api/http';

export interface BannerInfo {
    identifier: string;
    url: string;
    source: string | null;
    can_edit: boolean;
}

export interface Bootstrap {
    banners: Record<string, BannerInfo>;
}

let pending: Promise<Bootstrap> | null = null;

// Eine Abfrage fuer alle Karten; schlaegt sie fehl, gibt es eben keine Banner.
export const loadBootstrap = (): Promise<Bootstrap> => {
    if (!pending) {
        pending = http
            .get('/api/client/extensions/techtyl/bootstrap')
            .then((response) => {
                const attributes = (response.data && response.data.attributes) || {};
                const banners = attributes.banners && typeof attributes.banners === 'object' ? attributes.banners : {};
                return { banners } as Bootstrap;
            })
            .catch(() => {
                pending = null;
                return { banners: {} } as Bootstrap;
            });
    }
    return pending;
};

// Nur Pfade auf dem eigenen Panel oder https-Adressen, ohne Zeichen, die aus url("...") ausbrechen.
export const bannerValue = (url: string | undefined | null): string | undefined => {
    if (!url || url.length > 500) return undefined;
    const local = url.charAt(0) === '/' && url.charAt(1) !== '/';
    if (!(local || url.indexOf('https://') === 0)) return undefined;
    if (/["'()\<>\s]/.test(url)) return undefined;
    return 'url("' + url + '")';
};
