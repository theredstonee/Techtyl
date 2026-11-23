import { store } from '@/state';
import { Locale, resolveLocale, translate, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/tools';

/*
 * Texts of the server tools. Components use the hook:
 *     const t = useToolsT();   t('install')   t('hits', { n: 12 })
 * Helpers outside of components (formatters, error texts) use tt().
 * All texts live in dashboard/components/i18n/tools.ts (English and German).
 */

export type Translate = (key: string, replace?: Record<string, string | number>) => string;

export const useToolsT = (): Translate => useT(texts);

export const currentLocale = (): Locale => {
    const user = store.getState().user.data;

    return resolveLocale(user ? user.language : undefined);
};

export const tt: Translate = (key, replace) => translate(texts, currentLocale(), key, replace);
