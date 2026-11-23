/* Date, device and event texts for the account pages. All texts come from the dictionary (t). */

import { Locale } from '@blueprint/extensions/techtyl/i18n';
import { Translate } from './api';

const pad = (value: number) => (value < 10 ? '0' : '') + value;

const MONTHS: Record<Locale, string[]> = {
    en: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    de: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
};
const WEEKDAYS: Record<Locale, string[]> = {
    en: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    de: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
};

export const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;

export const shortDate = (date: Date, locale: Locale) =>
    locale === 'de'
        ? `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`
        : `${MONTHS.en[date.getMonth()].slice(0, 3)} ${date.getDate()}, ${date.getFullYear()}`;

export const monthYear = (date: Date, locale: Locale) => `${MONTHS[locale][date.getMonth()]} ${date.getFullYear()}`;

export const longDate = (date: Date, locale: Locale) =>
    locale === 'de'
        ? `${date.getDate()}. ${MONTHS.de[date.getMonth()]} ${date.getFullYear()}`
        : `${MONTHS.en[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;

const dayStart = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
export const dayKey = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
export const daysAgo = (date: Date) => Math.round((dayStart(new Date()) - dayStart(date)) / 86400000);

/** Heading of a day group: "Today", "Yesterday", "Monday, November 23". */
export const dayLabel = (t: Translate, locale: Locale, date: Date): string => {
    const days = daysAgo(date);
    if (days <= 0) return t('today');
    if (days === 1) return t('yesterday');
    const year = date.getFullYear() === new Date().getFullYear() ? '' : ` ${date.getFullYear()}`;
    const weekday = WEEKDAYS[locale][date.getDay()];
    const month = MONTHS[locale][date.getMonth()];

    return locale === 'de' ? `${weekday}, ${date.getDate()}. ${month}${year}` : `${weekday}, ${month} ${date.getDate()}${year ? `,${year}` : ''}`;
};

/** "just now", "5 min ago", "3 h ago", "yesterday 13:20", "Nov 3, 2026". */
export const relative = (t: Translate, locale: Locale, date: Date): string => {
    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    const days = daysAgo(date);
    if (minutes < 1) return t('just_now');
    if (minutes < 60) return t('minutes_ago', { count: minutes });
    if (minutes < 60 * 12 && days === 0) return t('hours_ago', { count: Math.floor(minutes / 60) });
    if (days === 0) return t('today_at', { time: clock(date) });
    if (days === 1) return t('yesterday_at', { time: clock(date) });
    if (days < 7) return `${WEEKDAYS[locale][date.getDay()]} ${clock(date)}`;

    return shortDate(date, locale);
};

/** How long someone has been a member. */
export const memberFor = (t: Translate, date: Date): string => {
    const days = Math.max(0, daysAgo(date));
    if (days < 1) return t('member_today');
    if (days === 1) return t('member_yesterday');
    if (days < 31) return t('member_days', { count: days });
    const months = Math.floor(days / 30.4);
    if (months < 12) return months === 1 ? t('member_month') : t('member_months', { count: months });
    const years = Math.floor(days / 365.25);

    return years === 1 ? t('member_year') : t('member_years', { count: years });
};

/* ------------------------------------------------------------------ devices */

export interface Device {
    browser: string;
    system: string;
    kind: 'desktop' | 'mobile' | 'tool';
}

export const parseUserAgent = (ua: string | null | undefined): Device | null => {
    if (!ua) return null;
    const has = (pattern: RegExp) => pattern.test(ua);
    if (has(/curl|wget|python|go-http|okhttp|axios|node-fetch|postman|insomnia|java\//i)) {
        const name = (ua.match(/^([A-Za-z-]+)/) || [])[1] || 'Script';

        return { browser: name, system: '', kind: 'tool' };
    }
    const browser = has(/Edg\//)
        ? 'Edge'
        : has(/OPR\/|Opera/)
        ? 'Opera'
        : has(/Vivaldi/)
        ? 'Vivaldi'
        : has(/SamsungBrowser/)
        ? 'Samsung Internet'
        : has(/Firefox\//)
        ? 'Firefox'
        : has(/Chrome\/|CriOS/)
        ? 'Chrome'
        : has(/Safari\//)
        ? 'Safari'
        : '';
    const system = has(/Windows/)
        ? 'Windows'
        : has(/iPhone|iPad|iPod/)
        ? 'iOS'
        : has(/Android/)
        ? 'Android'
        : has(/CrOS/)
        ? 'ChromeOS'
        : has(/Mac OS X|Macintosh/)
        ? 'macOS'
        : has(/Linux/)
        ? 'Linux'
        : '';
    const kind = has(/Mobile|iPhone|Android(?!.*Tablet)/) ? 'mobile' : 'desktop';

    return { browser, system, kind };
};

export const deviceLabel = (t: Translate, device: Device | null): string => {
    if (!device) return t('device_unknown');
    if (device.kind === 'tool') return t('device_tool', { name: device.browser });
    const browser = device.browser || t('device_browser');

    return device.system ? t('device_on', { browser, system: device.system }) : browser;
};

/* ------------------------------------------------------------------ events */

export type EventCategory = 'login' | 'fail' | 'security' | 'key' | 'account' | 'other';

const text = (value: unknown): string => (typeof value === 'string' || typeof value === 'number' ? String(value) : '');

const EVENTS: Record<string, [string, EventCategory]> = {
    'auth:success': ['ev_login', 'login'],
    'auth:fail': ['ev_login_failed', 'fail'],
    'auth:checkpoint': ['ev_checkpoint', 'login'],
    'auth:token': ['ev_token', 'login'],
    'auth:recovery-token': ['ev_recovery', 'security'],
    'auth:password-reset': ['ev_password_reset', 'security'],
    'event:password-reset': ['ev_password_reset', 'security'],
    'auth:reset-password': ['ev_reset_requested', 'security'],
    'auth:ip-blocked': ['ev_ip_blocked', 'fail'],
    'auth:sftp.fail': ['ev_sftp_failed', 'fail'],
    'user:user.create': ['ev_account_created', 'account'],
    'user:account.email-changed': ['ev_email_changed', 'account'],
    'user:account.password-changed': ['ev_password_changed', 'security'],
    'user:account.name-changed': ['ev_name_changed', 'account'],
    'user:account.language-changed': ['ev_language_changed', 'account'],
    'user:account.sessions-ended': ['ev_sessions_ended', 'security'],
    'user:api-key.create': ['ev_api_create', 'key'],
    'user:api-key.delete': ['ev_api_delete', 'key'],
    'user:ssh-key.create': ['ev_ssh_create', 'key'],
    'user:ssh-key.delete': ['ev_ssh_delete', 'key'],
    'user:two-factor.create': ['ev_totp_on', 'security'],
    'user:two-factor.delete': ['ev_totp_off', 'security'],
};

/** Readable sentence and category for an activity log event (values are inserted as plain text). */
export const describeEvent = (t: Translate, event: string, properties: Record<string, unknown>): { text: string; category: EventCategory } => {
    const known = EVENTS[event];
    if (!known) {
        return { text: event.replace(/^[a-z]+:/, '').replace(/[.-]/g, ' '), category: 'other' };
    }
    const values: Record<string, string> = {};
    ['old', 'new', 'identifier', 'fingerprint', 'language'].forEach((key) => {
        values[key] = text(properties[key]);
    });
    // Variant without values if the panel did not store them.
    const [key, category] = known;
    const needs = key === 'ev_email_changed' ? values.old && values.new : key === 'ev_name_changed' ? values.new : key.startsWith('ev_api_') ? values.identifier : true;

    return { text: t(needs ? key : `${key}_plain`, values), category };
};

/** Short fingerprint for display: "SHA256:ab12…ef90". */
export const shortFingerprint = (value: string) => (value.length > 26 ? `${value.slice(0, 16)}…${value.slice(-8)}` : value);

/* ------------------------------------------------------------------ password strength */

const COMMON = ['passwort', 'password', '123456', 'qwertz', 'qwerty', 'hallo', 'hello', 'admin', 'letmein', 'iloveyou', 'abc123', 'test'];

/** Rough estimate 0-100 % with a hint; helps choosing, the server rules still apply. */
export const passwordStrength = (t: Translate, value: string, context: string[] = []): { percent: number; label: string; hint: string } => {
    if (!value) return { percent: 0, label: '', hint: t('pw_hint_empty') };
    let pool = 0;
    if (/[a-z]/.test(value)) pool += 26;
    if (/[A-Z]/.test(value)) pool += 26;
    if (/\d/.test(value)) pool += 10;
    if (/[^a-zA-Z\d]/.test(value)) pool += 33;
    let bits = value.length * Math.log2(Math.max(pool, 1));
    const lower = value.toLowerCase();
    const weak: string[] = [];
    if (COMMON.some((word) => lower.includes(word))) {
        bits *= 0.45;
        weak.push(t('pw_weak_common'));
    }
    if (context.some((word) => word.length >= 3 && lower.includes(word.toLowerCase()))) {
        bits *= 0.55;
        weak.push(t('pw_weak_personal'));
    }
    if (/(.)\1{2,}/.test(value)) {
        bits *= 0.8;
        weak.push(t('pw_weak_repeat'));
    }
    if (/(0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef)/i.test(value)) {
        bits *= 0.8;
        weak.push(t('pw_weak_sequence'));
    }
    const percent = Math.max(4, Math.min(100, Math.round((bits / 80) * 100)));
    const short = value.length < 8;
    const label = short ? t('pw_too_short') : percent < 40 ? t('pw_weak') : percent < 65 ? t('pw_medium') : percent < 90 ? t('pw_strong') : t('pw_very_strong');
    const hint = short
        ? t('pw_hint_missing', { count: 8 - value.length })
        : weak.length > 0
        ? t('pw_hint_weak', { list: weak.join(', ') })
        : percent < 65
        ? t('pw_hint_longer')
        : t('pw_hint_good');

    return { percent: short ? Math.min(percent, 20) : percent, label, hint };
};
