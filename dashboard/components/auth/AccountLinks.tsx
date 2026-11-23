import React, { useEffect, useRef, useState } from 'react';
import http, { httpErrorToHuman } from '@/api/http';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/auth';
import './styles.css';

/*
 * Konto-Seite: Discord/Google mit dem eigenen Konto verknuepfen oder die Verknuepfung loesen.
 * Beides verlangt das Passwort; geprueft wird es auf dem Server.
 */

const BASE = '/extensions/techtyl/auth';
const AUTHORIZE = ['https://discord.com/', 'https://accounts.google.com/'];

interface Link {
    key: string;
    title: string;
    enabled: boolean;
    linked: boolean;
    label: string | null;
    linkedAt: string | null;
}

type Notice = { tone: 'ok' | 'bad'; text: string } | null;

// Result of the return from the provider (query parameter) => [tone, text key]
const RESULTS: Record<string, ['ok' | 'bad', string]> = {
    ok: ['ok', 'result_ok'],
    vergeben: ['bad', 'result_taken'],
    doppelt: ['bad', 'result_double'],
    fehler: ['bad', 'result_error'],
};

const readLinks = (): Promise<Link[]> =>
    http.get(BASE + '/links').then(({ data }) =>
        (Array.isArray(data?.providers) ? data.providers : []).map((entry: any) => ({
            key: String(entry.key),
            title: String(entry.title),
            enabled: entry.enabled === true,
            linked: entry.linked === true,
            label: typeof entry.label === 'string' ? entry.label : null,
            linkedAt: typeof entry.linked_at === 'string' ? entry.linked_at : null,
        }))
    );

const formatDate = (value: string | null, locale: string): string => {
    if (!value) return '';
    const date = new Date(value.replace(' ', 'T'));

    return isNaN(date.getTime())
        ? ''
        : date.toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

/** Liest das Ergebnis der Rueckkehr vom Anbieter aus der Adresse und raeumt sie danach auf. */
const takeResult = (): ['ok' | 'bad', string] | null => {
    const params = new URLSearchParams(window.location.search);
    const result = params.get('techtyl-link');
    if (!result) return null;
    params.delete('techtyl-link');
    const query = params.toString();
    window.history.replaceState(window.history.state, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);

    return RESULTS[result] || null;
};

export default () => {
    const [links, setLinks] = useState<Link[] | null>(null);
    const [notice, setNotice] = useState<Notice>(null);
    const [open, setOpen] = useState<string | null>(null);
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState('');
    const field = useRef<HTMLInputElement>(null);
    const t = useT(texts);
    const locale = useLocale();

    const load = () =>
        readLinks()
            .then(setLinks)
            .catch(() => setLinks([]));

    useEffect(() => {
        const result = takeResult();
        setNotice(result ? { tone: result[0], text: t(result[1]) } : null);
        load();
    }, []);

    useEffect(() => {
        if (open && field.current) field.current.focus();
    }, [open]);

    if (!links || links.length === 0) return null;

    const toggle = (key: string | null) => {
        setOpen(key);
        setPassword('');
        setProblem('');
    };

    const submit = (link: Link) => (event: React.FormEvent) => {
        event.preventDefault();
        if (busy || password === '') return;
        setBusy(true);
        setProblem('');
        setNotice(null);

        const request = link.linked
            ? http.delete(BASE + '/links/' + link.key, { data: { password } }).then(() => {
                  toggle(null);
                  setNotice({ tone: 'ok', text: t('unlinked', { provider: link.title }) });

                  return load();
              })
            : http.post(BASE + '/links/' + link.key, { password }).then(({ data }) => {
                  const url = typeof data?.url === 'string' ? data.url : '';
                  if (!AUTHORIZE.some((prefix) => url.indexOf(prefix) === 0)) {
                      throw new Error(t('provider_unreachable'));
                  }
                  window.location.assign(url);
              });

        request
            .catch((error) => {
                setProblem(
                    error?.response?.status === 429
                        ? t('too_many')
                        : httpErrorToHuman(error)
                );
                setPassword('');
                field.current && field.current.focus();
            })
            .then(() => setBusy(false));
    };

    return (
        <section className={'techtyl-auth-links'} aria-labelledby={'techtyl-auth-links-title'}>
            <header>
                <h2 id={'techtyl-auth-links-title'}>{t('links_title')}</h2>
                <p>{t('links_lead')}</p>
            </header>

            {notice && (
                <p className={'techtyl-auth-note techtyl-auth-note-' + notice.tone} role={'status'}>
                    {notice.text}
                </p>
            )}

            <ul>
                {links.map((link) => (
                    <li key={link.key} className={open === link.key ? 'is-open' : undefined}>
                        <div className={'techtyl-auth-link-row'}>
                            <i className={'techtyl-auth-tile techtyl-auth-provider-' + link.key} aria-hidden={'true'}>
                                {link.title.charAt(0)}
                            </i>
                            <div className={'techtyl-auth-link-text'}>
                                <strong>{link.title}</strong>
                                <span>
                                    {link.linked
                                        ? [
                                              t('linked'),
                                              link.label ? t('linked_as', { label: link.label }) : '',
                                              formatDate(link.linkedAt, locale) ? t('linked_since', { date: formatDate(link.linkedAt, locale) }) : '',
                                          ]
                                              .filter((part) => part !== '')
                                              .join(' ')
                                        : link.enabled
                                        ? t('not_linked')
                                        : t('disabled_now')}
                                </span>
                            </div>
                            {(link.linked || link.enabled) && open !== link.key && (
                                <button type={'button'} className={'techtyl-auth-button'} onClick={() => toggle(link.key)}>
                                    {link.linked ? t('unlink') : t('link')}
                                </button>
                            )}
                        </div>

                        {open === link.key && (
                            <form className={'techtyl-auth-confirm'} onSubmit={submit(link)}>
                                <label htmlFor={'techtyl-auth-password-' + link.key}>
                                    {link.linked ? t('confirm_unlink') : t('confirm_link', { provider: link.title })}
                                </label>
                                <div className={'techtyl-auth-confirm-row'}>
                                    <input
                                        ref={field}
                                        id={'techtyl-auth-password-' + link.key}
                                        type={'password'}
                                        autoComplete={'current-password'}
                                        maxLength={1024}
                                        value={password}
                                        disabled={busy}
                                        aria-invalid={problem !== ''}
                                        onChange={(event) => setPassword(event.target.value)}
                                    />
                                    <button type={'submit'} className={'techtyl-auth-button is-primary'} disabled={busy || password === ''}>
                                        {busy ? t('wait') : link.linked ? t('unlink_confirm') : t('continue_to', { provider: link.title })}
                                    </button>
                                    <button type={'button'} className={'techtyl-auth-button is-quiet'} disabled={busy} onClick={() => toggle(null)}>
                                        {t('cancel')}
                                    </button>
                                </div>
                                {problem !== '' && (
                                    <p className={'techtyl-auth-problem'} role={'alert'}>
                                        {problem}
                                    </p>
                                )}
                            </form>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
};
