// Translations for the Techtyl frontend. English is the default, German is available.
//
// Each area keeps its own dictionary file next to this one, e.g. ./tickets.ts:
//   export default { en: { title: 'Tickets', count: ':count open' }, de: { title: 'Tickets', count: ':count offen' } };
// and uses it like this:
//   import texts from '@blueprint/extensions/techtyl/i18n/tickets';
//   const t = useT(texts);
//   t('count', { count: 3 });
// Missing German texts fall back to English, missing keys to the key itself.

import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

export type Locale = 'en' | 'de';
export type Dictionary = { en: Record<string, string>; de?: Record<string, string> };

const supported: Locale[] = ['en', 'de'];

// Default language chosen by the administrator; the wrapper writes it into the page.
export const fallbackLocale = (): Locale => {
    const element = typeof document !== 'undefined' ? document.getElementById('techtyl-locale') : null;
    const value = element ? element.getAttribute('data-locale') || '' : '';

    return (supported as string[]).includes(value) ? (value as Locale) : 'en';
};

// The signed-in user's panel language if it is German, otherwise the administrator's default.
export const resolveLocale = (userLanguage?: string | null): Locale => {
    const language = (userLanguage || '').slice(0, 2).toLowerCase();

    return language === 'de' ? 'de' : fallbackLocale();
};

export const translate = (
    texts: Dictionary,
    locale: Locale,
    key: string,
    replace: Record<string, string | number> = {}
): string => {
    let text = (locale === 'de' && texts.de && texts.de[key]) || texts.en[key] || key;
    Object.keys(replace).forEach((placeholder) => {
        text = text.split(':' + placeholder).join(String(replace[placeholder]));
    });

    return text;
};

export const useLocale = (): Locale => {
    const language = useStoreState((state: ApplicationStore) => state.user.data?.language);

    return resolveLocale(language);
};

export const useT = (texts: Dictionary) => {
    const locale = useLocale();

    return (key: string, replace?: Record<string, string | number>) => translate(texts, locale, key, replace);
};

// For places outside the signed-in app (login, register): no user store available.
export const useGuestT = (texts: Dictionary) => {
    const locale = fallbackLocale();

    return (key: string, replace?: Record<string, string | number>) => translate(texts, locale, key, replace);
};
