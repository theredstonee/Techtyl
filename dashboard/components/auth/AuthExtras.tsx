import React, { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useGuestT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/auth';
import { attachToken, mountWidget } from './turnstile';
import './styles.css';

/*
 * Ergaenzt die Anmeldeseiten des Panels: Turnstile-Pruefung, Anmeldung ueber Discord/Google
 * und der Link zur Registrierung. Was davon erscheint, bestimmt der Admin; ist das Modul aus,
 * antwortet die Abfrage mit 404 und es erscheint nichts.
 */

const BASE = '/extensions/techtyl/auth';

interface Provider {
    key: string;
    title: string;
}

interface AuthConfig {
    registration: boolean;
    turnstile: string | null;
    providers: Provider[];
}

const readConfig = (): Promise<AuthConfig | null> =>
    fetch(BASE + '/config', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : null))
        .then((data) => {
            if (!data || typeof data !== 'object') return null;
            const providers: Provider[] = Array.isArray(data.providers)
                ? data.providers
                      .filter((entry: any) => entry && /^[a-z]{2,16}$/.test(entry.key) && typeof entry.title === 'string')
                      .map((entry: any) => ({ key: entry.key, title: entry.title }))
                : [];

            return {
                registration: data.registration === true,
                turnstile: typeof data.turnstile === 'string' && data.turnstile !== '' ? data.turnstile : null,
                providers,
            };
        })
        .catch(() => null);

const Check = ({ siteKey }: { siteKey: string }) => {
    const box = useRef<HTMLDivElement>(null);
    const [problem, setProblem] = useState(false);
    const t = useGuestT(texts);

    useEffect(() => {
        const detach = attachToken();
        const unmount = box.current ? mountWidget(box.current, siteKey, setProblem) : undefined;

        return () => {
            detach();
            unmount && unmount();
        };
    }, [siteKey]);

    return (
        <div className={'techtyl-auth-check'}>
            <div ref={box} />
            {problem && (
                <p className={'techtyl-auth-problem'} role={'alert'}>
                    {t('check_problem')}
                </p>
            )}
        </div>
    );
};

export default () => {
    const { pathname } = useLocation();
    const [config, setConfig] = useState<AuthConfig | null>(null);
    const t = useGuestT(texts);

    useEffect(() => {
        let active = true;
        readConfig().then((value) => active && setConfig(value));

        return () => {
            active = false;
        };
    }, []);

    if (!config) return null;

    const path = pathname.replace(/\/+$/, '');
    const onLogin = path === '/auth/login';
    const onForgot = path === '/auth/password';
    const showCheck = config.turnstile !== null && (onLogin || onForgot);
    const showWays = onLogin && (config.providers.length > 0 || config.registration);

    if (!showCheck && !showWays) return null;

    return (
        <div className={'techtyl-auth-extras'}>
            {showCheck && <Check siteKey={config.turnstile as string} />}

            {onLogin && config.providers.length > 0 && (
                <div className={'techtyl-auth-ways'}>
                    <p className={'techtyl-auth-or'}>
                        <span>{t('or')}</span>
                    </p>
                    <div className={'techtyl-auth-providers'}>
                        {config.providers.map((provider) => (
                            <a
                                key={provider.key}
                                className={'techtyl-auth-provider techtyl-auth-provider-' + provider.key}
                                href={BASE + '/oauth/' + provider.key + '/redirect'}
                            >
                                <i aria-hidden={'true'}>{provider.title.charAt(0)}</i>
                                <span>{t('sign_in_with', { provider: provider.title })}</span>
                            </a>
                        ))}
                    </div>
                </div>
            )}

            {onLogin && config.registration && (
                <p className={'techtyl-auth-register'}>
                    {t('no_account')} <a href={BASE + '/register'}>{t('create_account')}</a>
                </p>
            )}
        </div>
    );
};
