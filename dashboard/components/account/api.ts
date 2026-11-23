import http from '@/api/http';

/* Schnittstelle des Kontobereichs: /api/client/extensions/techtyl/account/... */

const BASE = '/api/client/extensions/techtyl/account';

export interface AvatarInfo {
    url: string | null;
    initials: string;
    color: string;
    custom?: boolean;
}

export interface AccountData {
    id: number;
    uuid: string;
    username: string;
    email: string;
    firstName: string;
    lastName: string;
    rootAdmin: boolean;
    useTotp: boolean;
    createdAt: Date | null;
    avatar: AvatarInfo;
    permissions: { uploadAvatar: boolean; changeName: boolean; changeUsername: boolean };
    limits: { maxUploadBytes: number; mimeTypes: string[]; avatarSize: number };
    modules: { tickets: boolean; billing: boolean; assistant: boolean };
    session: SessionInfo;
}

export interface SessionInfo {
    ip: string | null;
    userAgent: string;
}

const toAvatar = (raw: any): AvatarInfo => ({
    url: typeof raw?.url === 'string' && raw.url !== '' ? raw.url : null,
    initials: typeof raw?.initials === 'string' ? raw.initials : '?',
    color: typeof raw?.color === 'string' ? raw.color : '#f0a33b',
    custom: Boolean(raw?.custom),
});

const toAccount = (a: any): AccountData => ({
    id: Number(a.id),
    uuid: String(a.uuid || ''),
    username: String(a.username || ''),
    email: String(a.email || ''),
    firstName: String(a.first_name || ''),
    lastName: String(a.last_name || ''),
    rootAdmin: Boolean(a.root_admin),
    useTotp: Boolean(a.use_totp),
    createdAt: a.created_at ? new Date(a.created_at) : null,
    avatar: toAvatar(a.avatar),
    permissions: {
        uploadAvatar: Boolean(a.permissions?.upload_avatar),
        changeName: Boolean(a.permissions?.change_name),
        changeUsername: Boolean(a.permissions?.change_username),
    },
    limits: {
        maxUploadBytes: Number(a.limits?.max_upload_bytes || 0),
        mimeTypes: Array.isArray(a.limits?.mime_types) ? a.limits.mime_types : [],
        avatarSize: Number(a.limits?.avatar_size || 256),
    },
    modules: {
        tickets: Boolean(a.modules?.tickets),
        billing: Boolean(a.modules?.billing),
        assistant: Boolean(a.modules?.assistant),
    },
    session: { ip: a.session?.ip ?? null, userAgent: String(a.session?.user_agent || '') },
});

export const getAccount = async (): Promise<AccountData> => {
    const { data } = await http.get(`${BASE}/me`);

    return toAccount(data.attributes);
};

export const updateProfile = async (firstName: string, lastName: string): Promise<AccountData> => {
    const { data } = await http.patch(`${BASE}/profile`, { first_name: firstName, last_name: lastName });

    return toAccount(data.attributes);
};

export const uploadAvatar = async (image: Blob, onProgress?: (percent: number) => void): Promise<AvatarInfo> => {
    const form = new FormData();
    const extension = image.type === 'image/png' ? 'png' : image.type === 'image/webp' ? 'webp' : 'jpg';
    form.append('image', image, `profilbild.${extension}`);
    const { data } = await http.post(`${BASE}/avatar`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event: { loaded: number; total?: number }) => {
            if (onProgress && event.total) onProgress(Math.round((event.loaded / event.total) * 100));
        },
    });

    return toAvatar(data.attributes);
};

export const deleteAvatar = async (): Promise<AvatarInfo> => {
    const { data } = await http.delete(`${BASE}/avatar`);

    return toAvatar(data.attributes);
};

export const lookupAvatars = async (ids: number[]): Promise<Record<number, AvatarInfo>> => {
    const { data } = await http.get(`${BASE}/avatars`, { params: { ids: ids.join(',') } });
    const out: Record<number, AvatarInfo> = {};
    Object.keys(data.data || {}).forEach((key) => {
        out[Number(key)] = toAvatar(data.data[key]);
    });

    return out;
};

export const getSecurity = async (): Promise<{ session: SessionInfo; canLogoutOthers: boolean }> => {
    const { data } = await http.get(`${BASE}/security`);
    const a = data.attributes || {};

    return {
        session: { ip: a.session?.ip ?? null, userAgent: String(a.session?.user_agent || '') },
        canLogoutOthers: Boolean(a.can_logout_others),
    };
};

export const logoutOtherSessions = async (password: string): Promise<void> => {
    await http.post(`${BASE}/sessions/logout-others`, { password });
};

export const updateLanguage = async (language: 'en' | 'de'): Promise<void> => {
    await http.patch(`${BASE}/language`, { language });
};

/* ------------------------------------------------------------------ panel activity log */

export interface ActivityEntry {
    id: string;
    event: string;
    ip: string | null;
    isApi: boolean;
    properties: Record<string, unknown>;
    timestamp: Date;
}

export interface ActivityPage {
    items: ActivityEntry[];
    page: number;
    totalPages: number;
    total: number;
}

/** Own account activity from the panel API (/api/client/account/activity), newest first. */
export const getActivity = async (options: { event?: string; page?: number; perPage?: number } = {}): Promise<ActivityPage> => {
    const params: Record<string, unknown> = { sort: '-timestamp', page: options.page || 1, per_page: options.perPage || 25 };
    if (options.event) params['filter[event]'] = options.event;
    const { data } = await http.get('/api/client/account/activity', { params });
    const pagination = data.meta?.pagination || {};

    return {
        items: (data.data || []).map((row: any) => {
            const a = row.attributes || {};
            const properties = a.properties && typeof a.properties === 'object' && !Array.isArray(a.properties) ? a.properties : {};

            return {
                id: String(a.id),
                event: String(a.event || ''),
                ip: a.ip || null,
                isApi: Boolean(a.is_api),
                properties,
                timestamp: new Date(a.timestamp),
            };
        }),
        page: Number(pagination.current_page || 1),
        totalPages: Number(pagination.total_pages || 1),
        total: Number(pagination.total || 0),
    };
};

/* ------------------------------------------------------------------ error texts */

export type Translate = (key: string, replace?: Record<string, string | number>) => string;

/** Known English messages of the panel mapped to dictionary keys (so German users get German). */
const KNOWN: [RegExp, string][] = [
    [/password provided was not valid|invalid_password|password you entered was invalid|password does not match/i, 'err_password'],
    [/two-factor authentication token.*invalid|token provided is not valid/i, 'err_totp_code'],
    [/already enabled/i, 'err_totp_enabled'],
    [/email has already been taken|email.*already.*taken/i, 'err_email_taken'],
    [/email.*must be a valid/i, 'err_email_invalid'],
    [/changed too many times/i, 'err_email_throttle'],
    [/confirmation does not match/i, 'err_password_confirm'],
    [/password.*at least 8/i, 'err_password_short'],
    [/too many (requests|attempts)/i, 'err_too_many'],
    [/public key.*(invalid|not valid)|could not be parsed|ssh key.*invalid|key provided is not valid/i, 'err_ssh_invalid'],
    [/key.*already exists|fingerprint.*already|already been added/i, 'err_ssh_exists'],
    [/(rsa|key).*(2048|too (short|weak))/i, 'err_ssh_rsa'],
    [/maximum number of|reached the account limit|limit of/i, 'err_key_limit'],
    [/description.*required/i, 'err_description'],
    [/allowed.?ips.*(invalid|valid ip)|not a valid ip/i, 'err_ips'],
    [/network error/i, 'err_network'],
];

/**
 * Human error message. Own endpoints already answer in the user's language (I18n on the server);
 * known panel messages are translated, anything else falls back to a generic text.
 */
export const explain = (t: Translate, error: any, fallbackKey = 'err_generic'): string => {
    const status = error?.response?.status;
    const data = error?.response?.data;
    let detail = '';
    if (data && Array.isArray(data.errors) && data.errors[0]?.detail) {
        detail = String(data.errors[0].detail);
    } else if (data && typeof data.message === 'string') {
        detail = data.message;
    } else if (typeof error?.message === 'string') {
        detail = error.message;
    }
    for (const [pattern, key] of KNOWN) {
        if (pattern.test(detail)) return t(key);
    }
    if (status === 419) return t('err_session');
    if (status === 429) return t('err_too_many');
    if (status === 413) return t('err_too_large');
    if (status && status >= 500) return t('err_server');
    // Techtyl endpoints answer in the user's language already; show their message as it is.
    const own = String(error?.config?.url || '').indexOf('/extensions/techtyl/') !== -1;
    if (detail && status && status < 500 && own) return detail;
    if (status === 403) return t('err_forbidden');

    return t(fallbackKey);
};
