import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Prompt } from 'react-router-dom';
import http, { httpErrorToHuman } from '@/api/http';
import getServers from '@/api/getServers';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/designer';
import AccountLayout from '@blueprint/extensions/techtyl/account/Layout';
import Icon from '@blueprint/extensions/techtyl/designer/icons';
import {
    Bootstrap,
    Design,
    MOTION,
    NAVIGATION,
    SERVER_LAYOUTS,
    SCENES,
    SCENE_FOR_VARIANT,
    VARIANTS,
    imageValue,
    loadBootstrap,
    refreshBootstrap,
} from '@blueprint/extensions/techtyl/designer/bootstrap';
import { accentIsLight, applyDesign, resolveBackground } from '@blueprint/extensions/techtyl/designer/apply';
import '@blueprint/extensions/techtyl/designer/styles.css';

type Translate = (key: string, replace?: Record<string, string | number>) => string;
type Group = 'appearance' | 'layout' | 'background';
type DesignKey = keyof Design;

const BASE = '/api/client/extensions/techtyl';

// Keys a user may set, grouped by the administrator permission that unlocks them (as on the server).
const GROUPS: Record<Group, DesignKey[]> = {
    appearance: ['variant', 'accent', 'radius', 'motion'],
    layout: ['navigation', 'server_layout', 'banners', 'banner_height'],
    background: ['dim', 'opacity', 'blur', 'scene'],
};

const SWATCHES = ['#f0a33b', '#ef6f5e', '#e86a9a', '#a885f2', '#5aa9f0', '#2ec4b6', '#7cc48a', '#b8d44a', '#e8c35a', '#c8794a', '#9fb2c4', '#e9f0ee'];

// Colours of the four variants for the small previews (same as the stylesheet).
const PALETTES: Record<string, [string, string, string, string]> = {
    werft: ['#0f1c23', '#172a33', '#2c4753', '#e9f0ee'],
    papier: ['#e9ecef', '#ffffff', '#c9d0d8', '#161c2b'],
    hafen: ['#190f24', '#2a1b3a', '#4c3766', '#f5edf7'],
    kiefer: ['#121f19', '#1b2d24', '#35503f', '#eef0e2'],
};

type Background = 'default' | 'none' | 'own' | string;

const sizeLabel = (bytes: number): string =>
    bytes >= 1048576 ? (Math.round((bytes / 1048576) * 10) / 10).toString().replace('.', ',') + ' MB' : Math.round(bytes / 1024) + ' KB';

const initialBackground = (data: Bootstrap): Background => {
    if (data.background.custom_url) return 'own';
    const motif = data.userDesign.motif;
    if (motif === 'none') return 'none';
    if (motif && data.motifs.some((item) => item.key === motif)) return motif;
    return 'default';
};

/* ---------- small building blocks ---------- */

function Section({ title, locked, children, t }: { title: string; locked: boolean; children: React.ReactNode; t: Translate }) {
    return (
        <section className={'techtyl-designer-section' + (locked ? ' is-locked' : '')}>
            <header className={'techtyl-designer-section-head'}>
                <h2>{title}</h2>
                {locked && (
                    <span className={'techtyl-designer-lock'} title={t('locked_text')}>
                        <Icon name={'lock'} />
                        {t('locked')}
                    </span>
                )}
            </header>
            {locked && <p className={'techtyl-designer-lock-text'}>{t('locked_text')}</p>}
            <fieldset disabled={locked} className={'techtyl-designer-fieldset'}>
                {children}
            </fieldset>
        </section>
    );
}

interface ChoiceOption {
    value: string;
    title: string;
    desc?: string;
    art?: React.ReactNode;
}

function Choices({ label, options, value, onPick, columns }: { label: string; options: ChoiceOption[]; value: string; onPick: (value: string) => void; columns?: number }) {
    const refs = useRef<(HTMLButtonElement | null)[]>([]);
    const active = Math.max(0, options.findIndex((option) => option.value === value));
    const onKey = (event: React.KeyboardEvent, index: number) => {
        const step = ({ ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 } as Record<string, number>)[event.key];
        if (step === undefined) return;
        event.preventDefault();
        const next = (index + step + options.length) % options.length;
        onPick(options[next].value);
        const button = refs.current[next];
        if (button) button.focus();
    };
    return (
        <div className={'techtyl-designer-choices'} role={'radiogroup'} aria-label={label} style={{ ['--cols' as any]: String(columns || options.length) }}>
            {options.map((option, index) => (
                <button
                    key={option.value}
                    ref={(element) => {
                        refs.current[index] = element;
                    }}
                    type={'button'}
                    role={'radio'}
                    aria-checked={option.value === value}
                    tabIndex={index === active ? 0 : -1}
                    className={'techtyl-designer-choice'}
                    onClick={() => onPick(option.value)}
                    onKeyDown={(event) => onKey(event, index)}
                >
                    {option.art && <span className={'techtyl-designer-choice-art'}>{option.art}</span>}
                    <span className={'techtyl-designer-choice-title'}>{option.title}</span>
                    {option.desc && <span className={'techtyl-designer-choice-desc'}>{option.desc}</span>}
                    <span className={'techtyl-designer-choice-check'} aria-hidden>
                        <Icon name={'check'} />
                    </span>
                </button>
            ))}
        </div>
    );
}

function Slider({ label, value, min, max, step, unit, onChange, disabled }: { label: string; value: number; min: number; max: number; step?: number; unit: string; onChange: (value: number) => void; disabled?: boolean }) {
    const fill = ((value - min) / (max - min)) * 100;
    return (
        <label className={'techtyl-designer-slider'}>
            <span className={'techtyl-designer-slider-label'}>
                <span>{label}</span>
                <output>{value + ' ' + unit}</output>
            </span>
            <input
                type={'range'}
                min={min}
                max={max}
                step={step || 1}
                value={value}
                disabled={disabled}
                aria-valuetext={value + ' ' + unit}
                style={{ ['--fill' as any]: fill + '%' }}
                onChange={(event) => onChange(Number(event.currentTarget.value))}
            />
        </label>
    );
}

function VariantArt({ variant, accent }: { variant: string; accent: string }) {
    const [bg, surface, line, text] = PALETTES[variant] || PALETTES.werft;
    return (
        <svg viewBox={'0 0 120 72'} aria-hidden>
            <rect width={120} height={72} fill={bg} />
            <rect width={28} height={72} fill={surface} />
            <rect x={5} y={7} width={14} height={4} rx={1.5} fill={text} />
            <rect x={5} y={18} width={18} height={4} rx={1.5} fill={accent} />
            <rect x={5} y={26} width={15} height={3} rx={1.5} fill={line} />
            <rect x={5} y={33} width={17} height={3} rx={1.5} fill={line} />
            {[34, 78].map((x) => (
                <g key={x}>
                    <rect x={x} y={9} width={38} height={26} rx={3} fill={surface} />
                    <rect x={x} y={9} width={38} height={9} rx={3} fill={accent} opacity={0.4} />
                    <rect x={x + 4} y={23} width={20} height={3} rx={1.5} fill={text} />
                    <rect x={x} y={40} width={38} height={24} rx={3} fill={surface} />
                    <rect x={x + 4} y={47} width={22} height={3} rx={1.5} fill={text} />
                    <rect x={x + 4} y={55} width={30} height={2} rx={1} fill={line} />
                </g>
            ))}
        </svg>
    );
}

function LayoutArt({ kind }: { kind: string }) {
    const card = (x: number, y: number, w: number, h: number, key: string) => (
        <g key={key}>
            <rect x={x} y={y} width={w} height={h} rx={3} className={'s-surface'} />
            <rect x={x} y={y} width={w} height={h * 0.35} rx={3} className={'s-accent-soft'} />
            <rect x={x + 3} y={y + h * 0.55} width={w * 0.5} height={3} rx={1.5} className={'s-text'} />
        </g>
    );
    let shapes: React.ReactNode[] = [];
    if (kind === 'sidebar') shapes = [<rect key={'n'} width={30} height={72} className={'s-surface'} />, card(36, 8, 38, 26, 'a'), card(78, 8, 36, 26, 'b'), card(36, 38, 38, 26, 'c'), card(78, 38, 36, 26, 'd')];
    if (kind === 'icons') shapes = [<rect key={'n'} width={12} height={72} className={'s-surface'} />, card(18, 8, 46, 26, 'a'), card(68, 8, 46, 26, 'b'), card(18, 38, 46, 26, 'c'), card(68, 38, 46, 26, 'd')];
    if (kind === 'top') shapes = [<rect key={'n'} width={120} height={12} className={'s-surface'} />, card(6, 18, 52, 24, 'a'), card(62, 18, 52, 24, 'b'), card(6, 46, 52, 22, 'c'), card(62, 46, 52, 22, 'd')];
    if (kind === 'raster') shapes = [card(8, 8, 50, 26, 'a'), card(62, 8, 50, 26, 'b'), card(8, 38, 50, 26, 'c'), card(62, 38, 50, 26, 'd')];
    if (kind === 'liste')
        shapes = [8, 24, 40, 56].map((y) => (
            <g key={y}>
                <rect x={8} y={y} width={104} height={12} rx={3} className={'s-surface'} />
                <rect x={8} y={y} width={26} height={12} rx={3} className={'s-accent-soft'} />
                <rect x={40} y={y + 4.5} width={30} height={3} rx={1.5} className={'s-text'} />
            </g>
        ));
    if (kind === 'kompakt') {
        for (let row = 0; row < 3; row++) for (let col = 0; col < 3; col++) shapes.push(card(8 + col * 36, 6 + row * 21, 32, 18, row + '-' + col));
    }
    return (
        <svg viewBox={'0 0 120 72'} aria-hidden className={'techtyl-designer-sketch'}>
            <rect width={120} height={72} className={'s-bg'} />
            {shapes}
        </svg>
    );
}

function SceneArt({ kind, variant, accent }: { kind: string; variant: string; accent: string }) {
    const palette = PALETTES[variant] || PALETTES.werft;
    const bg = palette[0];
    const surface = palette[1];
    const line = palette[2];
    const text = palette[3];
    const drawn = kind === 'auto' ? SCENE_FOR_VARIANT[variant] || 'contours' : kind;
    const stars: Array<[number, number]> = [
        [12, 14],
        [28, 8],
        [46, 18],
        [70, 10],
        [92, 16],
        [108, 8],
        [20, 32],
        [40, 28],
        [64, 36],
        [84, 30],
        [104, 40],
        [16, 50],
        [36, 56],
        [58, 48],
        [80, 58],
        [100, 50],
    ];
    const trees: Array<[number, number, number]> = [
        [16, 58, 18],
        [34, 62, 24],
        [54, 56, 16],
        [74, 64, 28],
        [96, 58, 20],
        [112, 62, 16],
    ];
    return (
        <span className={'techtyl-designer-scene-art'}>
            <svg viewBox={'0 0 120 72'} aria-hidden>
                <rect width={120} height={72} fill={bg} />
                {drawn === 'contours' &&
                    [1, 2, 3, 4, 5].map((step) => (
                        <ellipse key={step} cx={60} cy={78} rx={14 * step} ry={7 * step} fill={'none'} stroke={step % 2 === 0 ? accent : line} strokeWidth={1} />
                    ))}
                {drawn === 'aurora' && (
                    <>
                        <path d={'M0 22 C24 4 48 34 78 14 C98 2 110 12 120 8 L120 0 L0 0 Z'} fill={accent} opacity={0.45} />
                        <path d={'M0 40 C30 20 62 50 96 28 C108 20 114 26 120 22 L120 0 L0 0 Z'} fill={text} opacity={0.16} />
                        <path d={'M0 58 C36 40 70 66 120 46 L120 72 L0 72 Z'} fill={surface} />
                    </>
                )}
                {drawn === 'stars' &&
                    stars.map((dot, index) => <circle key={index} cx={dot[0]} cy={dot[1]} r={index % 4 === 0 ? 1.3 : 0.7} fill={index % 5 === 0 ? accent : text} />)}
                {drawn === 'forest' && (
                    <>
                        <rect y={58} width={120} height={14} fill={surface} />
                        {trees.map((tree, index) => {
                            const x = tree[0];
                            const y = tree[1];
                            const height = tree[2];
                            const half = Math.round(height * 0.42);
                            return <polygon key={index} points={x - half + ',' + y + ' ' + (x + half) + ',' + y + ' ' + x + ',' + (y - height)} fill={index % 2 === 0 ? surface : line} />;
                        })}
                    </>
                )}
                {drawn === 'harbor' && (
                    <>
                        <rect y={40} width={120} height={32} fill={surface} />
                        <path d={'M0 46 C24 40 40 50 60 44 C80 38 96 48 120 42'} fill={'none'} stroke={line} strokeWidth={1} />
                        <path d={'M0 58 C30 52 50 62 80 56 C100 52 110 58 120 54'} fill={'none'} stroke={line} strokeWidth={1} />
                        <rect x={48} y={28} width={18} height={14} rx={1} fill={surface} stroke={line} strokeWidth={0.6} />
                        <rect x={51} y={31} width={3} height={2.5} fill={accent} />
                        <rect x={57} y={31} width={3} height={2.5} fill={accent} opacity={0.65} />
                        <circle cx={100} cy={16} r={4} fill={text} opacity={0.35} />
                        <circle cx={22} cy={36} r={1.4} fill={accent} />
                    </>
                )}
                {drawn === 'grid' && (
                    <>
                        {[0, 1, 2, 3, 4, 5, 6].map((step) => (
                            <line key={'v' + step} x1={step * 20} y1={0} x2={step * 20} y2={72} stroke={line} strokeWidth={1} />
                        ))}
                        {[0, 1, 2, 3].map((step) => (
                            <line key={'h' + step} x1={0} y1={step * 24} x2={120} y2={step * 24} stroke={line} strokeWidth={1} />
                        ))}
                        <circle cx={60} cy={36} r={2.2} fill={accent} />
                    </>
                )}
            </svg>
        </span>
    );
}

function MotionArt({ level }: { level: string }) {
    return (
        <svg viewBox={'0 0 120 72'} aria-hidden className={'techtyl-designer-sketch techtyl-designer-motion'} data-level={level}>
            <rect width={120} height={72} className={'s-bg'} />
            {[10, 44, 78].map((x, index) => (
                <g key={x} className={'m-card m-' + (index + 1)}>
                    <rect x={x} y={14} width={30} height={34} rx={3} className={'s-surface'} />
                    <rect x={x} y={14} width={30} height={11} rx={3} className={'s-accent-soft'} />
                    <rect x={x + 3} y={31} width={16} height={3} rx={1.5} className={'s-text'} />
                </g>
            ))}
            <path d={'M10 60 H42 L47 53 L53 66 L58 60 H110'} className={'m-pulse'} />
        </svg>
    );
}

/* ---------- banner management ---------- */

interface ServerRow {
    uuid: string;
    name: string;
}

function Banners({ data, t, onChanged, onNotice }: { data: Bootstrap; t: Translate; onChanged: () => void; onNotice: (tone: 'ok' | 'bad', text: string) => void }) {
    const [servers, setServers] = useState<ServerRow[] | null>(null);
    const [more, setMore] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const inputs = useRef<Record<string, HTMLInputElement | null>>({});

    useEffect(() => {
        getServers({ page: 1 })
            .then((result) => {
                setServers(result.items.map((server) => ({ uuid: server.uuid, name: server.name })));
                setMore(result.pagination.totalPages > 1);
            })
            .catch(() => setServers([]));
    }, []);

    const upload = (uuid: string, file: File) => {
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            onNotice('bad', t('error_type'));
            return;
        }
        if (file.size > data.maxUploadBytes) {
            onNotice('bad', t('error_size', { size: sizeLabel(data.maxUploadBytes) }));
            return;
        }
        const body = new FormData();
        body.append('image', file);
        setBusy(uuid);
        http.post(`${BASE}/servers/${uuid}/banner`, body)
            .then(() => {
                onNotice('ok', t('uploaded'));
                onChanged();
            })
            .catch((error) => onNotice('bad', httpErrorToHuman(error)))
            .then(() => setBusy(null));
    };

    const remove = (uuid: string) => {
        setBusy(uuid);
        http.delete(`${BASE}/servers/${uuid}/banner`)
            .then(() => {
                onNotice('ok', t('removed'));
                onChanged();
            })
            .catch((error) => onNotice('bad', httpErrorToHuman(error)))
            .then(() => setBusy(null));
    };

    if (servers === null) return <div className={'techtyl-designer-skeleton'} aria-busy />;
    const rows = servers.filter((server) => data.banners[server.uuid]);
    if (!rows.length) return <p className={'techtyl-designer-empty'}>{t('banners_empty')}</p>;

    return (
        <>
            <ul className={'techtyl-designer-banners'}>
                {rows.map((server) => {
                    const banner = data.banners[server.uuid];
                    const url = imageValue(banner.url);
                    const source = banner.source || 'none';
                    const editable = banner.can_edit;
                    return (
                        <li key={server.uuid} className={'techtyl-designer-banner' + (busy === server.uuid ? ' is-busy' : '')}>
                            <span className={'techtyl-designer-banner-image'}>
                                {url ? <img src={url} alt={''} loading={'lazy'} style={{ objectPosition: data.bannerPosition }} /> : <span className={'techtyl-designer-banner-blank'} />}
                            </span>
                            <span className={'techtyl-designer-banner-text'}>
                                <strong>{server.name}</strong>
                                <span className={'techtyl-designer-banner-source is-' + source}>{t('banner_source_' + source)}</span>
                            </span>
                            <span className={'techtyl-designer-banner-actions'}>
                                {editable ? (
                                    <>
                                        <input
                                            ref={(element) => {
                                                inputs.current[server.uuid] = element;
                                            }}
                                            type={'file'}
                                            accept={'image/png,image/jpeg,image/webp'}
                                            className={'techtyl-designer-sr'}
                                            tabIndex={-1}
                                            aria-hidden
                                            onChange={(event) => {
                                                const file = event.currentTarget.files && event.currentTarget.files[0];
                                                event.currentTarget.value = '';
                                                if (file) upload(server.uuid, file);
                                            }}
                                        />
                                        <button type={'button'} className={'techtyl-designer-btn'} disabled={busy !== null} onClick={() => inputs.current[server.uuid]?.click()}>
                                            <Icon name={'upload'} />
                                            {source === 'server' ? t('banner_replace') : t('banner_upload')}
                                        </button>
                                        {source === 'server' && (
                                            <button type={'button'} className={'techtyl-designer-btn is-ghost'} disabled={busy !== null} onClick={() => remove(server.uuid)}>
                                                <Icon name={'trash'} />
                                                {t('banner_remove')}
                                            </button>
                                        )}
                                    </>
                                ) : (
                                    <span className={'techtyl-designer-note'}>{data.permissions.banners ? t('banner_no_right') : t('banner_locked')}</span>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ul>
            {more && <p className={'techtyl-designer-note'}>{t('banners_more', { count: servers.length })}</p>}
        </>
    );
}

/* ---------- page ---------- */

export default () => {
    const t = useT(texts) as Translate;
    const [data, setData] = useState<Bootstrap | null>(null);
    const [failed, setFailed] = useState(false);
    const [draft, setDraft] = useState<Partial<Design>>({});
    const [background, setBackground] = useState<Background>('default');
    const [saving, setSaving] = useState(false);
    const [confirmReset, setConfirmReset] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'ok' | 'bad'; text: string } | null>(null);
    const fileInput = useRef<HTMLInputElement | null>(null);
    const savedLook = useRef<{ design: Design; background: string; motifs: Bootstrap['motifs'] } | null>(null);
    const noticeTimer = useRef<number | undefined>(undefined);

    const showNotice = useCallback((tone: 'ok' | 'bad', text: string) => {
        setNotice({ tone, text });
        window.clearTimeout(noticeTimer.current);
        noticeTimer.current = window.setTimeout(() => setNotice(null), tone === 'bad' ? 7000 : 3500);
    }, []);

    const adopt = useCallback((fresh: Bootstrap) => {
        setData(fresh);
        setDraft({ ...fresh.userDesign });
        setBackground(initialBackground(fresh));
        savedLook.current = { design: fresh.design, background: fresh.background.url, motifs: fresh.motifs };
    }, []);

    const load = useCallback(() => {
        setFailed(false);
        loadBootstrap().then(adopt).catch(() => setFailed(true));
    }, [adopt]);

    useEffect(() => {
        load();
        return () => {
            // Leaving without saving: back to the saved look.
            if (savedLook.current) applyDesign(savedLook.current.design, savedLook.current.background, savedLook.current.motifs);
            window.clearTimeout(noticeTimer.current);
        };
    }, []);

    const allowed = (group: Group): boolean => !!data && data.permissions[group];

    // The look the user sees: own values for unlocked groups, otherwise the administrator's.
    const effective = useMemo((): Design | null => {
        if (!data) return null;
        const out: Design = { ...data.adminDesign };
        (Object.keys(GROUPS) as Group[]).forEach((group) => {
            if (!data.permissions[group]) return;
            GROUPS[group].forEach((key) => {
                if (draft[key] !== undefined && draft[key] !== null) (out as any)[key] = draft[key];
            });
        });
        return out;
    }, [data, draft]);

    const backgroundUrl = useMemo((): string => {
        if (!data || !effective) return '';
        if (!data.permissions.background) return resolveBackground(data.adminDesign.background, effective.variant, data.motifs);
        if (background === 'own' && data.background.custom_url) return data.background.custom_url;
        if (background === 'none') return '';
        const motif = data.motifs.find((item) => item.key === background);
        if (motif) return motif.url;
        return resolveBackground(data.adminDesign.background, effective.variant, data.motifs);
    }, [data, effective, background]);

    useEffect(() => {
        if (effective && data) applyDesign(effective, backgroundUrl, data.motifs);
    }, [effective, backgroundUrl, data]);

    const savedBackground = data ? initialBackground(data) : 'default';
    const changes = useMemo(() => {
        if (!data) return 0;
        let count = 0;
        (Object.keys(GROUPS) as Group[]).forEach((group) => {
            if (!data.permissions[group]) return;
            GROUPS[group].forEach((key) => {
                if ((draft[key] ?? null) !== ((data.userDesign as any)[key] ?? null)) count++;
            });
        });
        if (data.permissions.background && background !== savedBackground) count++;
        return count;
    }, [data, draft, background, savedBackground]);
    const dirty = changes > 0;

    useEffect(() => {
        if (!dirty) return undefined;
        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const set = <K extends DesignKey>(key: K, value: Design[K]) => setDraft((current) => ({ ...current, [key]: value }));

    const payload = (reset: boolean): Record<string, unknown> => {
        const design: Record<string, unknown> = {};
        if (!data) return design;
        (Object.keys(GROUPS) as Group[]).forEach((group) => {
            if (!data.permissions[group]) return;
            GROUPS[group].forEach((key) => {
                design[key] = reset ? null : draft[key] ?? null;
            });
        });
        if (data.permissions.background) {
            design.motif = reset || background === 'default' || background === 'own' ? null : background;
        }
        return design;
    };

    const persist = (reset: boolean) => {
        if (!data) return;
        setSaving(true);
        const dropUpload = data.background.custom_url && (reset || background !== 'own');
        const steps: Promise<unknown> = dropUpload ? http.delete(`${BASE}/background`) : Promise.resolve();
        const design = payload(reset);
        steps
            .then(() => (Object.keys(design).length ? http.patch(`${BASE}/settings`, { design }) : null))
            .then(() => refreshBootstrap())
            .then((fresh) => {
                adopt(fresh);
                showNotice('ok', reset ? t('reset_done') : t('saved'));
            })
            .catch((error) => showNotice('bad', httpErrorToHuman(error) || t('error_generic')))
            .then(() => {
                setSaving(false);
                setConfirmReset(false);
            });
    };

    const discard = () => {
        if (!data) return;
        setDraft({ ...data.userDesign });
        setBackground(initialBackground(data));
    };

    const uploadBackground = (file: File) => {
        if (!data) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            showNotice('bad', t('error_type'));
            return;
        }
        if (file.size > data.maxUploadBytes) {
            showNotice('bad', t('error_size', { size: sizeLabel(data.maxUploadBytes) }));
            return;
        }
        const body = new FormData();
        body.append('image', file);
        setSaving(true);
        http.post(`${BASE}/background`, body)
            .then(() => refreshBootstrap())
            .then((fresh) => {
                // Keep unsaved changes of other values, only the image is new.
                setData(fresh);
                setBackground('own');
                savedLook.current = { design: fresh.design, background: fresh.background.url, motifs: fresh.motifs };
                showNotice('ok', t('uploaded'));
            })
            .catch((error) => showNotice('bad', httpErrorToHuman(error)))
            .then(() => setSaving(false));
    };

    const reloadBanners = () => {
        refreshBootstrap()
            .then((fresh) => setData(fresh))
            .catch(() => undefined);
    };

    let body: React.ReactNode;
    if (failed) {
        body = (
            <div className={'techtyl-designer-state'}>
                <p>{t('load_error')}</p>
                <button type={'button'} className={'techtyl-designer-btn'} onClick={load}>
                    {t('retry')}
                </button>
            </div>
        );
    } else if (!data || !effective) {
        body = (
            <div className={'techtyl-designer-state'} aria-busy>
                <span className={'techtyl-designer-skeleton'} />
                <span className={'techtyl-designer-sr'}>{t('loading')}</span>
            </div>
        );
    } else {
        const adminBackground = resolveBackground(data.adminDesign.background, effective.variant, data.motifs);
        const backgroundOptions: ChoiceOption[] = [
            { value: 'default', title: t('bg_default'), art: adminBackground ? <img src={adminBackground} alt={''} loading={'lazy'} /> : <span className={'techtyl-designer-solid'} /> },
            { value: 'none', title: t('bg_none'), art: <span className={'techtyl-designer-solid'} /> },
            ...data.motifs.map((motif) => ({ value: motif.key, title: motif.name, art: <img src={motif.url} alt={''} loading={'lazy'} /> })),
        ];
        if (data.background.custom_url) {
            backgroundOptions.push({ value: 'own', title: t('bg_own'), art: <img src={data.background.custom_url} alt={''} /> });
        }

        body = (
            <div className={'techtyl-designer-page'}>
                <div className={'techtyl-designer-top'}>
                    <p className={'techtyl-designer-intro'}>{t('intro')}</p>
                    {confirmReset ? (
                        <div className={'techtyl-designer-confirm'} role={'alertdialog'} aria-label={t('reset_admin')}>
                            <span>{t('reset_confirm')}</span>
                            <button type={'button'} className={'techtyl-designer-btn is-ghost'} onClick={() => setConfirmReset(false)} disabled={saving}>
                                {t('discard')}
                            </button>
                            <button type={'button'} className={'techtyl-designer-btn is-danger'} onClick={() => persist(true)} disabled={saving} autoFocus>
                                {t('reset_admin')}
                            </button>
                        </div>
                    ) : (
                        <button
                            type={'button'}
                            className={'techtyl-designer-btn is-ghost'}
                            onClick={() => setConfirmReset(true)}
                            disabled={saving || !(data.permissions.appearance || data.permissions.layout || data.permissions.background)}
                        >
                            <Icon name={'undo'} />
                            {t('reset_admin')}
                        </button>
                    )}
                </div>

                <Section title={t('sec_style')} locked={!allowed('appearance')} t={t}>
                    <h3 className={'techtyl-designer-label'}>{t('variant')}</h3>
                    <Choices
                        label={t('variant')}
                        columns={4}
                        value={effective.variant}
                        onPick={(value) => set('variant', value)}
                        options={VARIANTS.map((variant) => ({ value: variant, title: t('variant_' + variant), art: <VariantArt variant={variant} accent={effective.accent} /> }))}
                    />
                    <h3 className={'techtyl-designer-label'}>{t('accent')}</h3>
                    <div className={'techtyl-designer-swatches'} role={'radiogroup'} aria-label={t('accent')}>
                        {SWATCHES.map((hex) => (
                            <button
                                key={hex}
                                type={'button'}
                                role={'radio'}
                                aria-checked={effective.accent === hex}
                                aria-label={hex}
                                title={hex}
                                className={'techtyl-designer-swatch'}
                                style={{ ['--sw' as any]: hex }}
                                onClick={() => set('accent', hex)}
                            />
                        ))}
                        <label className={'techtyl-designer-swatch is-custom'} title={t('accent_custom')}>
                            <input type={'color'} value={effective.accent} aria-label={t('accent_custom')} onChange={(event) => set('accent', event.currentTarget.value.toLowerCase())} />
                        </label>
                        <span className={'techtyl-designer-ink ' + (accentIsLight(effective.accent) ? 'is-dark' : 'is-light')}>{effective.accent}</span>
                    </div>
                    <Slider label={t('corners')} value={effective.radius} min={0} max={22} unit={'px'} onChange={(value) => set('radius', value)} />
                </Section>

                <Section title={t('sec_background')} locked={!allowed('background')} t={t}>
                    <Choices label={t('sec_background')} columns={4} value={background} onPick={setBackground} options={backgroundOptions} />
                    <div className={'techtyl-designer-row'}>
                        <input
                            ref={fileInput}
                            type={'file'}
                            accept={'image/png,image/jpeg,image/webp'}
                            className={'techtyl-designer-sr'}
                            tabIndex={-1}
                            aria-hidden
                            onChange={(event) => {
                                const file = event.currentTarget.files && event.currentTarget.files[0];
                                event.currentTarget.value = '';
                                if (file) uploadBackground(file);
                            }}
                        />
                        <button type={'button'} className={'techtyl-designer-btn'} onClick={() => fileInput.current?.click()} disabled={saving}>
                            <Icon name={'upload'} />
                            {data.background.custom_url ? t('bg_replace') : t('bg_upload')}
                        </button>
                        <span className={'techtyl-designer-note'}>{t('bg_hint', { size: sizeLabel(data.maxUploadBytes) })}</span>
                    </div>
                    <h3 className={'techtyl-designer-label'}>{t('scene')}</h3>
                    <Choices
                        label={t('scene')}
                        columns={4}
                        value={effective.scene || 'auto'}
                        onPick={(value) => set('scene', value)}
                        options={[
                            { value: 'auto', title: t('scene_auto'), desc: t('scene_auto_desc'), art: <SceneArt kind={'auto'} variant={effective.variant} accent={effective.accent} /> },
                            { value: 'none', title: t('scene_none'), art: <SceneArt kind={'none'} variant={effective.variant} accent={effective.accent} /> },
                            ...SCENES.map((key) => ({
                                value: key,
                                title: t('scene_' + key),
                                art: <SceneArt kind={key} variant={effective.variant} accent={effective.accent} />,
                            })),
                        ]}
                    />
                    <p className={'techtyl-designer-note'}>{t('scene_hint')}</p>
                    <Slider label={t('dim')} value={effective.dim} min={0} max={90} unit={'%'} disabled={!backgroundUrl} onChange={(value) => set('dim', value)} />
                    <Slider label={t('opacity')} value={effective.opacity} min={35} max={100} unit={'%'} onChange={(value) => set('opacity', value)} />
                    <Slider label={t('blur')} value={effective.blur} min={0} max={24} unit={'px'} onChange={(value) => set('blur', value)} />
                </Section>

                <Section title={t('sec_layout')} locked={!allowed('layout')} t={t}>
                    <h3 className={'techtyl-designer-label'}>{t('navigation')}</h3>
                    <Choices
                        label={t('navigation')}
                        columns={3}
                        value={effective.navigation}
                        onPick={(value) => set('navigation', value)}
                        options={NAVIGATION.map((kind) => ({ value: kind, title: t('nav_' + kind), art: <LayoutArt kind={kind} /> }))}
                    />
                    <h3 className={'techtyl-designer-label'}>{t('servers')}</h3>
                    <Choices
                        label={t('servers')}
                        columns={3}
                        value={effective.server_layout}
                        onPick={(value) => set('server_layout', value)}
                        options={SERVER_LAYOUTS.map((kind) => ({ value: kind, title: t('servers_' + kind), art: <LayoutArt kind={kind} /> }))}
                    />
                    <h3 className={'techtyl-designer-label'}>{t('banners')}</h3>
                    <Choices
                        label={t('banners')}
                        columns={2}
                        value={effective.banners ? 'an' : 'aus'}
                        onPick={(value) => set('banners', value === 'an')}
                        options={[
                            { value: 'an', title: t('banners_on') },
                            { value: 'aus', title: t('banners_off') },
                        ]}
                    />
                    <Slider label={t('banner_height')} value={effective.banner_height} min={48} max={160} step={4} unit={'px'} disabled={!effective.banners} onChange={(value) => set('banner_height', value)} />
                </Section>

                <Section title={t('sec_motion')} locked={!allowed('appearance')} t={t}>
                    <Choices
                        label={t('sec_motion')}
                        columns={3}
                        value={effective.motion}
                        onPick={(value) => set('motion', value)}
                        options={MOTION.map((level) => ({ value: level, title: t('motion_' + level), desc: t('motion_' + level + '_desc'), art: <MotionArt level={level} /> }))}
                    />
                </Section>

                <section className={'techtyl-designer-section'}>
                    <header className={'techtyl-designer-section-head'}>
                        <h2>{t('sec_banners')}</h2>
                        {!data.permissions.banners && (
                            <span className={'techtyl-designer-lock'}>
                                <Icon name={'lock'} />
                                {t('locked')}
                            </span>
                        )}
                    </header>
                    <p className={'techtyl-designer-note'}>{t('banners_intro')}</p>
                    <Banners data={data} t={t} onChanged={reloadBanners} onNotice={showNotice} />
                </section>
            </div>
        );
    }

    return (
        <AccountLayout title={t('page_title')} subtitle={t('subtitle')}>
            <Prompt when={dirty} message={t('leave_prompt')} />
            {body}
            <div className={'techtyl-designer-savebar' + (dirty || saving ? ' is-open' : '')} aria-hidden={!(dirty || saving)}>
                <span className={'techtyl-designer-savebar-dot'} aria-hidden />
                <span className={'techtyl-designer-savebar-text'}>{t(changes === 1 ? 'unsaved_one' : 'unsaved_many', { count: changes })}</span>
                <button type={'button'} className={'techtyl-designer-btn is-ghost'} onClick={discard} disabled={saving} tabIndex={dirty ? 0 : -1}>
                    {t('discard')}
                </button>
                <button type={'button'} className={'techtyl-designer-btn is-go'} onClick={() => persist(false)} disabled={saving} tabIndex={dirty ? 0 : -1}>
                    {saving ? t('saving') : t('save')}
                </button>
            </div>
            <div className={'techtyl-designer-toast-wrap'} aria-live={'polite'}>
                {notice && (
                    <div className={'techtyl-designer-toast is-' + notice.tone} role={notice.tone === 'bad' ? 'alert' : 'status'}>
                        <Icon name={notice.tone === 'ok' ? 'check' : 'close'} />
                        {notice.text}
                    </div>
                )}
            </div>
        </AccountLayout>
    );
};
