import http, { httpErrorToHuman } from '@/api/http';

const BASE = '/api/client/extensions/techtyl/billing';

export type SubscriptionStatus = 'pending' | 'active' | 'past_due' | 'canceled' | 'ended';

export interface LedgerEntry {
    id: number;
    amount: number;
    balance_after: number;
    reason: 'topup' | 'daily' | 'admin' | 'refund';
    note: string;
    created_at: string;
}

export interface Subscription {
    id: number;
    kind: 'stripe' | 'balance';
    status: SubscriptionStatus;
    package: string;
    server_name: string;
    server_identifier: string | null;
    price: number;
    currency: string;
    provision: 'none' | 'provisioning' | 'done' | 'failed';
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    suspended: boolean;
    suspend_on: string | null;
    delete_on: string | null;
    server_deleted: boolean;
    created_at: string | null;
}

export interface PackageEgg {
    id: number;
    label: string;
    group: string;
}

export interface Package {
    id: number;
    title: string;
    description: string;
    memory: number;
    cpu: number;
    disk: number;
    databases: number;
    backups: number;
    allocations: number;
    price_month: number;
    price_day: number | null;
    eggs: PackageEgg[];
}

export interface Overview {
    ready: boolean;
    test_mode: boolean;
    currency: string;
    balance: number;
    daily_cost: number;
    days_left: number | null;
    topup_amounts: number[];
    min_balance: number;
    suspend_days: number;
    delete_days: number;
    terms_url: string;
    withdrawal_url: string;
    has_customer: boolean;
    ledger: LedgerEntry[];
    subscriptions: Subscription[];
    packages: Package[];
}

export interface OrderInput {
    package_id: number;
    egg_id: number;
    server_name: string;
    method: 'stripe' | 'balance';
}

export const loadOverview = async (): Promise<Overview> => {
    const { data } = await http.get(`${BASE}/overview`);

    return data.attributes as Overview;
};

/** Liefert die Stripe-Adresse, zu der weitergeleitet wird. */
export const startTopup = async (amount: number): Promise<string> => {
    const { data } = await http.post(`${BASE}/topup`, { amount });

    return String(data.attributes.url);
};

/** Stripe-Abo: Adresse des Checkouts. Guthaben: das angelegte Abo. */
export const placeOrder = async (input: OrderInput): Promise<{ url?: string; subscription?: Subscription }> => {
    const { data } = await http.post(`${BASE}/order`, { ...input, confirmed: true });
    if (data.object === 'techtyl_billing_redirect') {
        return { url: String(data.attributes.url) };
    }

    return { subscription: data.attributes as Subscription };
};

export const cancelSubscription = async (id: number): Promise<Subscription> => {
    const { data } = await http.post(`${BASE}/subscriptions/${id}/cancel`, {});

    return data.attributes as Subscription;
};

export const resumeSubscription = async (id: number): Promise<Subscription> => {
    const { data } = await http.post(`${BASE}/subscriptions/${id}/resume`, {});

    return data.attributes as Subscription;
};

export const openPortal = async (): Promise<string> => {
    const { data } = await http.post(`${BASE}/portal`, {});

    return String(data.attributes.url);
};

type Translate = (key: string, replace?: Record<string, string | number>) => string;

/** A readable message for an API error; never internal details. */
export const errorText = (error: unknown, t: Translate): string => {
    if (error instanceof Error && error.message === 'techtyl-redirect') {
        return t('redirect_failed');
    }
    const status = (error as { response?: { status?: number } })?.response?.status;
    if (status === 429) {
        return t('err_rate');
    }
    if (status === 404) {
        return t('err_gone');
    }
    const text = httpErrorToHuman(error);

    return text && text.length < 300 ? text : t('err_generic');
};

/** Redirects go to Stripe over https only; anything else is refused. */
export const goToStripe = (url: string): void => {
    let parsed: URL;
    try {
        parsed = new URL(url);
    } catch (e) {
        throw new Error('techtyl-redirect');
    }
    if (parsed.protocol !== 'https:' || !(parsed.hostname === 'stripe.com' || parsed.hostname.endsWith('.stripe.com'))) {
        throw new Error('techtyl-redirect');
    }
    window.location.assign(parsed.toString());
};

const SYMBOLS: Record<string, string> = {
    eur: '€',
    usd: '$',
    gbp: '£',
    chf: 'CHF',
    pln: 'zł',
    czk: 'Kč',
    sek: 'kr',
    nok: 'kr',
    dkk: 'kr',
};
const PREFIXED = ['eur', 'usd', 'gbp'];

/** 799 -> "€7.99" (en) or "7,99 €" (de). Works on whole cents only. */
export const money = (cents: number, currency: string, locale: string): string => {
    const german = locale === 'de';
    const abs = Math.abs(Math.trunc(cents));
    const whole = Math.floor(abs / 100)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, german ? '.' : ',');
    const fraction = (abs % 100).toString().padStart(2, '0');
    const symbol = SYMBOLS[currency] || currency.toUpperCase();
    const sign = cents < 0 ? '−' : '';
    if (german) {
        return `${sign}${whole},${fraction} ${symbol}`;
    }

    return PREFIXED.includes(currency) ? `${sign}${symbol}${whole}.${fraction}` : `${sign}${whole}.${fraction} ${symbol}`;
};

const tag = (locale: string): string => (locale === 'de' ? 'de-DE' : 'en-GB');

export const day = (iso: string | null, locale: string): string => {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    if (isNaN(date.getTime())) {
        return '';
    }

    return locale === 'de'
        ? date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

export const dayTime = (iso: string, locale: string): string => {
    const date = new Date(iso);
    if (isNaN(date.getTime())) {
        return '';
    }
    const time = date.toLocaleTimeString(tag(locale), { hour: '2-digit', minute: '2-digit' });

    return `${day(iso, locale)}, ${time}`;
};

/** 2048 MB -> "2 GB", 512 MB -> "512 MB". */
export const size = (megabytes: number, locale: string): string => {
    if (megabytes >= 1024) {
        const tenths = Math.round((megabytes / 1024) * 10);
        const text = tenths % 10 === 0 ? String(tenths / 10) : (tenths / 10).toFixed(1);

        return `${locale === 'de' ? text.replace('.', ',') : text} GB`;
    }

    return `${megabytes} MB`;
};
