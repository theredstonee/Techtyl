import http from '@/api/http';
import { fallbackLocale } from '@blueprint/extensions/techtyl/i18n';

/*
 * Turnstile auf den Anmeldeseiten des Panels. Das Widget liefert ein Token; ein Interceptor
 * haengt es an POST /auth/login und POST /auth/password. Der Server prueft es verbindlich,
 * dieser Code transportiert es nur.
 */

const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
const FIELD = 'cf-turnstile-response';
const PROTECTED = ['/auth/login', '/auth/password'];

interface TurnstileApi {
    render: (element: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id?: string) => void;
    remove: (id?: string) => void;
}

let token = '';
let widget: string | null = null;
let waiting: Array<(value: string) => void> = [];
let scriptPromise: Promise<TurnstileApi> | null = null;

const api = (): TurnstileApi | undefined => (window as unknown as { turnstile?: TurnstileApi }).turnstile;

const publish = (value: string) => {
    token = value;
    if (value) {
        waiting.forEach((resolve) => resolve(value));
        waiting = [];
    }
};

const loadScript = (): Promise<TurnstileApi> => {
    if (!scriptPromise) {
        scriptPromise = new Promise<TurnstileApi>((resolve, reject) => {
            const existing = api();
            if (existing) {
                resolve(existing);
                return;
            }
            const tag = document.createElement('script');
            tag.src = SCRIPT;
            tag.async = true;
            tag.defer = true;
            tag.onload = () => {
                const loaded = api();
                loaded ? resolve(loaded) : reject(new Error('turnstile'));
            };
            tag.onerror = () => {
                scriptPromise = null;
                reject(new Error('turnstile'));
            };
            document.head.appendChild(tag);
        });
    }

    return scriptPromise;
};

/** Zeigt das Widget im Element an. onProblem meldet, wenn es nicht geladen oder geloest werden kann. */
export const mountWidget = (element: HTMLElement, siteKey: string, onProblem: (problem: boolean) => void): (() => void) => {
    let cancelled = false;

    loadScript()
        .then((turnstile) => {
            if (cancelled) return;
            widget = turnstile.render(element, {
                sitekey: siteKey,
                action: 'techtyl',
                language: fallbackLocale(),
                size: 'flexible',
                theme: document.documentElement.getAttribute('data-techtyl-variant') === 'papier' ? 'light' : 'dark',
                callback: (value: string) => {
                    onProblem(false);
                    publish(value);
                },
                'expired-callback': () => publish(''),
                'error-callback': () => {
                    publish('');
                    onProblem(true);
                },
            });
        })
        .catch(() => !cancelled && onProblem(true));

    return () => {
        cancelled = true;
        publish('');
        const turnstile = api();
        if (turnstile && widget !== null) {
            try {
                turnstile.remove(widget);
            } catch (error) {
                /* Widget war schon weg */
            }
        }
        widget = null;
    };
};

/** Wartet kurz auf ein Token; ohne Token geht die Anfrage leer hinaus und der Server lehnt ab. */
const takeToken = (): Promise<string> => {
    if (token) {
        const value = token;
        token = '';

        return Promise.resolve(value);
    }

    return new Promise((resolve) => {
        const done = (value: string) => {
            window.clearTimeout(timer);
            token = '';
            resolve(value);
        };
        const timer = window.setTimeout(() => {
            waiting = waiting.filter((entry) => entry !== done);
            resolve('');
        }, 12000);
        waiting.push(done);
    });
};

const isProtected = (method?: string, url?: string): boolean =>
    (method || '').toLowerCase() === 'post' && PROTECTED.indexOf(url || '') !== -1;

/** Jedes Token gilt nur einmal: nach einer geschuetzten Anfrage ein neues holen. */
const renew = (method?: string, url?: string) => {
    if (!isProtected(method, url)) return;
    token = '';
    const turnstile = api();
    if (turnstile && widget !== null) {
        try {
            turnstile.reset(widget);
        } catch (error) {
            /* naechster Seitenaufruf rendert neu */
        }
    }
};

/** Haengt das Token an die geschuetzten Anfragen. Rueckgabe: Funktion zum Entfernen. */
export const attachToken = (): (() => void) => {
    const request = http.interceptors.request.use((config) => {
        if (!isProtected(config.method, config.url)) {
            return config;
        }

        return takeToken().then((value) => {
            let body: Record<string, unknown> = {};
            if (typeof config.data === 'string') {
                try {
                    body = JSON.parse(config.data);
                } catch (error) {
                    body = {};
                }
            } else if (config.data && typeof config.data === 'object') {
                body = config.data;
            }
            config.data = { ...body, [FIELD]: value };

            return config;
        });
    });

    const response = http.interceptors.response.use(
        (result) => {
            renew(result.config?.method, result.config?.url);

            return result;
        },
        (error) => {
            renew(error?.config?.method, error?.config?.url);

            throw error;
        }
    );

    return () => {
        http.interceptors.request.eject(request);
        http.interceptors.response.eject(response);
    };
};
