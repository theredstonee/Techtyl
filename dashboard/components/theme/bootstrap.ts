import { useEffect, useState } from 'react';
import http from '@/api/http';
import { cleanHidden, cleanIntegrations, EMPTY_HIDDEN, EMPTY_INTEGRATIONS, Integrations, NavHidden } from './integrate';

export interface BannerInfo {
    identifier: string;
    url: string;
    source: string | null;
    can_edit: boolean;
}

export interface Bootstrap {
    banners: Record<string, BannerInfo>;
    integrations: Integrations;
    navHidden: NavHidden;
}

const emptyBootstrap = (): Bootstrap => ({
    banners: {},
    integrations: EMPTY_INTEGRATIONS,
    navHidden: EMPTY_HIDDEN,
});

let pending: Promise<Bootstrap> | null = null;

// Eine Abfrage fuer Karten, fremde Erweiterungsseiten und ausgeblendete Eintraege.
export const loadBootstrap = (): Promise<Bootstrap> => {
    if (!pending) {
        pending = http
            .get('/api/client/extensions/techtyl/bootstrap')
            .then((response) => {
                const attributes = (response.data && response.data.attributes) || {};
                const banners = attributes.banners && typeof attributes.banners === 'object' ? attributes.banners : {};
                return {
                    banners,
                    integrations: cleanIntegrations(attributes.integrations),
                    navHidden: cleanHidden(attributes.nav_hidden),
                };
            })
            .catch(() => {
                pending = null;
                return emptyBootstrap();
            });
    }
    return pending;
};

// Shared by the navigation, the account column and the command palette.
export const useShellBootstrap = (): Bootstrap => {
    const [data, setData] = useState<Bootstrap>(emptyBootstrap);

    useEffect(() => {
        let active = true;
        loadBootstrap().then((loaded) => {
            if (active) setData(loaded);
        });

        return () => {
            active = false;
        };
    }, []);

    return data;
};

// Nur Pfade auf dem eigenen Panel oder https-Adressen, ohne Zeichen, die aus url("...") ausbrechen.
export const bannerValue = (url: string | undefined | null): string | undefined => {
    if (!url || url.length > 500) return undefined;
    const local = url.charAt(0) === '/' && url.charAt(1) !== '/';
    if (!(local || url.indexOf('https://') === 0)) return undefined;
    if (/["'()\<>\s]/.test(url)) return undefined;
    return 'url("' + url + '")';
};
