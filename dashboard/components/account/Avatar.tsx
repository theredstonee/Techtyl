import React, { useEffect, useState } from 'react';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import { AvatarInfo, getAccount, lookupAvatars } from './api';
import './styles.css';

/*
 * Profilbild als wiederverwendbare Komponente.
 *
 *   <Avatar self size={36} />                          eigenes Profilbild, aktualisiert sich nach Upload/Entfernen
 *   <Avatar userId={12} name={'Max Muster'} size={28} />  fremdes Profilbild (gebuendelt nachgeladen)
 *   <Avatar url={avatarUrl} name={'Max Muster'} />        Adresse ist schon bekannt (z. B. aus eigener API)
 *
 * Ohne Bild erscheinen Initialen auf einer aus dem Namen abgeleiteten Farbe (gleiche Regel wie
 * Avatar::color() in PHP). Bilder liegen hinter der Anmeldung, <img> schickt das Sitzungs-Cookie mit.
 */

export const AVATAR_PALETTE = ['#f0a33b', '#5fc4b0', '#f0857a', '#9fd67e', '#b49cf0', '#6fb3ee', '#e7c66a', '#ee8fb4'];
export const AVATAR_INK = '#10161b';
export const AVATAR_EVENT = 'techtyl:avatar';

const utf8 = (value: string): number[] => {
    const out: number[] = [];
    const encoded = unescape(encodeURIComponent(value));
    for (let i = 0; i < encoded.length; i++) out.push(encoded.charCodeAt(i));

    return out;
};

/** FNV-1a (32 Bit) ueber die UTF-8-Bytes, identisch zu Avatar::color() im Backend. */
export const avatarColor = (seed: string): string => {
    let hash = 0x811c9dc5;
    for (const byte of utf8(seed)) {
        hash ^= byte;
        hash = Math.imul(hash, 0x01000193) >>> 0;
    }

    return AVATAR_PALETTE[hash % AVATAR_PALETTE.length];
};

export const avatarSeed = (first: string, last: string, username = ''): string => {
    const name = `${first.trim()} ${last.trim()}`.trim().toLowerCase();

    return name !== '' ? name : username.trim().toLowerCase();
};

export const avatarInitials = (first: string, last: string, username = ''): string => {
    const pick = (value: string) => Array.from(value.trim())[0]?.toUpperCase() || '';
    const out = pick(first) + pick(last);
    if (out !== '') return out;

    return Array.from(username.trim()).slice(0, 2).join('').toUpperCase() || '?';
};

/** Aus einem freien Namen ("Max Muster" oder "mmuster") Initialen und Farbe bilden. */
const fromName = (name: string): Pick<AvatarInfo, 'initials' | 'color'> => {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0] || '';
    const last = parts.length > 1 ? parts[parts.length - 1] : '';
    const initials = parts.length > 1 ? avatarInitials(first, last) : avatarInitials('', '', first);

    return { initials, color: avatarColor(parts.length > 1 ? avatarSeed(first, last) : avatarSeed('', '', first)) };
};

/* ------------------------------------------------------------------ eigenes Profilbild */

let own: AvatarInfo | null = null;
let ownLoading: Promise<AvatarInfo | null> | null = null;
const ownListeners = new Set<(info: AvatarInfo | null) => void>();

/** Neues eigenes Profilbild bekannt machen (nach Upload/Entfernen); alle <Avatar self> folgen. */
export const publishOwnAvatar = (info: AvatarInfo | null) => {
    own = info;
    ownListeners.forEach((listener) => listener(info));
    try {
        window.dispatchEvent(new CustomEvent(AVATAR_EVENT, { detail: info }));
    } catch (e) {
        // aeltere Browser ohne CustomEvent-Konstruktor: Komponenten sind bereits informiert
    }
};

const loadOwn = (): Promise<AvatarInfo | null> => {
    if (!ownLoading) {
        ownLoading = getAccount()
            .then((account) => {
                publishOwnAvatar(account.avatar);

                return account.avatar;
            })
            .catch(() => {
                ownLoading = null;

                return null;
            });
    }

    return ownLoading;
};

/** Hook fuer das eigene Profilbild: { avatar, reload }. Mit enabled=false bleibt er still. */
export const useOwnAvatar = (enabled = true) => {
    const [avatar, setAvatar] = useState<AvatarInfo | null>(own);

    useEffect(() => {
        if (!enabled) return undefined;
        ownListeners.add(setAvatar);
        if (!own) loadOwn();
        else setAvatar(own);

        return () => {
            ownListeners.delete(setAvatar);
        };
    }, [enabled]);

    return {
        avatar,
        reload: () => {
            ownLoading = null;

            return loadOwn();
        },
    };
};

/* ------------------------------------------------------------------ fremde Profilbilder */

const known = new Map<number, AvatarInfo | null>();
const pending = new Set<number>();
const waiting = new Set<() => void>();
let timer: number | null = null;

const flush = () => {
    timer = null;
    const ids = Array.from(pending).slice(0, 100);
    ids.forEach((id) => pending.delete(id));
    if (pending.size > 0) timer = window.setTimeout(flush, 0);
    if (ids.length === 0) return;
    lookupAvatars(ids)
        .then((found) => ids.forEach((id) => known.set(id, found[id] || null)))
        .catch(() => ids.forEach((id) => known.set(id, null)))
        .then(() => waiting.forEach((notify) => notify()));
};

const request = (ids: number[]) => {
    ids.forEach((id) => {
        if (!known.has(id) && id > 0) pending.add(id);
    });
    if (pending.size > 0 && timer === null) timer = window.setTimeout(flush, 15);
};

/** Profilbilder mehrerer Nutzer; Anfragen aus demselben Moment werden zu einer gebuendelt. */
export const useAvatars = (ids: number[]): Record<number, AvatarInfo | null> => {
    const key = ids.filter((id) => id > 0).sort((a, b) => a - b).join(',');
    const [, setTick] = useState(0);

    useEffect(() => {
        const notify = () => setTick((value) => value + 1);
        waiting.add(notify);
        request(key === '' ? [] : key.split(',').map(Number));

        return () => {
            waiting.delete(notify);
        };
    }, [key]);

    const out: Record<number, AvatarInfo | null> = {};
    (key === '' ? [] : key.split(',').map(Number)).forEach((id) => {
        out[id] = known.has(id) ? known.get(id)! : null;
    });

    return out;
};

/* ------------------------------------------------------------------ Komponente */

export interface AvatarProps {
    /** Eigenes Profilbild (live). */
    self?: boolean;
    /** Nutzer-ID fuer ein fremdes Profilbild. */
    userId?: number;
    /** Bekannte Bildadresse (hat Vorrang vor userId). */
    url?: string | null;
    /** Anzeigename fuer Initialen und Farbe, z. B. "Max Muster" oder der Benutzername. */
    name?: string;
    /** Kantenlaenge in Pixeln (Standard 32). */
    size?: number;
    className?: string;
    /** Rahmen in Flaechenfarbe, z. B. wenn das Bild ueber einem Banner liegt. */
    ring?: boolean;
}

export default function Avatar({ self, userId, url, name, size = 32, className, ring }: AvatarProps) {
    const { avatar: ownAvatar } = useOwnAvatar(!!self);
    const others = useAvatars(!self && userId && url === undefined ? [userId] : []);
    const remote = self ? ownAvatar : userId ? others[userId] : null;
    const [broken, setBroken] = useState<string | null>(null);
    const t = useT(texts);

    const fallback = name ? fromName(name) : null;
    const initials = remote?.initials && !name ? remote.initials : fallback?.initials || remote?.initials || '?';
    const color = remote?.color && !name ? remote.color : fallback?.color || remote?.color || AVATAR_PALETTE[0];
    const src = url !== undefined ? url : remote?.url || null;
    const showImage = !!src && broken !== src;

    return (
        <span
            className={`techtyl-account-avatar${ring ? ' techtyl-account-avatar--ring' : ''}${className ? ` ${className}` : ''}`}
            style={{ width: size, height: size, background: color, color: AVATAR_INK, fontSize: Math.max(8, Math.round(size * 0.4)) }}
            role={'img'}
            aria-label={name ? t('avatar_of', { name }) : t('avatar')}
        >
            <span className={'techtyl-account-avatar-initials'} aria-hidden>
                {initials}
            </span>
            {showImage && (
                <img src={src!} alt={''} loading={'lazy'} draggable={false} onError={() => setBroken(src)} />
            )}
        </span>
    );
}
