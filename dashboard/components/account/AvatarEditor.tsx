import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { CameraIcon, PhotographIcon, TrashIcon, ZoomInIcon, ZoomOutIcon } from '@heroicons/react/outline';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import Avatar from './Avatar';
import { deleteAvatar, explain, uploadAvatar } from './api';
import { patchAccount, useAccount } from './store';
import { Button, Modal, Notice, Saved, useSaved } from './ui';

/*
 * Profile picture: pick, crop a square in the browser (own canvas cropper, no packages), upload.
 * The browser sends a PNG of the chosen square; the server checks it again with the shared image
 * pipeline and stores a 256 x 256 version without metadata.
 */

const MAX_SOURCE_BYTES = 20 * 1024 * 1024;
const OUTPUT_SIZES = [512, 384, 256];
const MAX_ZOOM = 4;

/** Type from the first bytes, independent of name and browser guess. */
const sniff = async (file: Blob): Promise<string | null> => {
    const head = new Uint8Array(await new Response(file.slice(0, 12)).arrayBuffer());
    const is = (bytes: number[], offset = 0) => bytes.every((byte, index) => head[offset + index] === byte);
    if (is([0xff, 0xd8, 0xff])) return 'image/jpeg';
    if (is([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])) return 'image/png';
    if (is([0x52, 0x49, 0x46, 0x46]) && is([0x57, 0x45, 0x42, 0x50], 8)) return 'image/webp';

    return null;
};

const loadImage = (url: string): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('decode'));
        image.src = url;
    });

const toBlob = (canvas: HTMLCanvasElement, type: string, quality?: number): Promise<Blob | null> =>
    new Promise((resolve) => canvas.toBlob((blob) => resolve(blob), type, quality));

interface View {
    zoom: number;
    x: number;
    y: number;
}

export default function AvatarEditor() {
    const t = useT(texts);
    const { account } = useAccount();
    const input = useRef<HTMLInputElement>(null);
    const [source, setSource] = useState<{ url: string; image: HTMLImageElement } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [removing, setRemoving] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [saved, flashSaved] = useSaved();

    useEffect(() => () => {
        if (source) URL.revokeObjectURL(source.url);
    }, [source]);

    if (!account) return null;
    const allowed = account.permissions.uploadAvatar;
    const displayName = `${account.firstName} ${account.lastName}`.trim() || account.username;

    const pick = async (file: File | undefined | null) => {
        setError(null);
        if (!file) return;
        if (file.size > MAX_SOURCE_BYTES) {
            setError(t('avatar_err_source_size'));

            return;
        }
        const type = await sniff(file).catch(() => null);
        if (!type) {
            setError(t('avatar_err_type'));

            return;
        }
        const url = URL.createObjectURL(file);
        try {
            const image = await loadImage(url);
            if (image.naturalWidth < 64 || image.naturalHeight < 64) {
                URL.revokeObjectURL(url);
                setError(t('avatar_err_small'));

                return;
            }
            setSource({ url, image });
        } catch (e) {
            URL.revokeObjectURL(url);
            setError(t('avatar_err_decode'));
        }
    };

    const remove = () => {
        setRemoving(true);
        setError(null);
        deleteAvatar()
            .then((avatar) => {
                patchAccount({ avatar });
                flashSaved();
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setRemoving(false));
    };

    return (
        <div className={'techtyl-account-avatar-edit'}>
            <div
                className={`techtyl-account-drop${dragOver ? ' is-over' : ''}${allowed ? '' : ' is-off'}`}
                onDragOver={(event) => {
                    if (!allowed) return;
                    event.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragOver(false);
                    if (allowed) pick(event.dataTransfer.files[0]);
                }}
            >
                <Avatar self name={displayName} size={112} ring />
                <div className={'techtyl-account-drop-text'}>
                    <strong>{account.avatar.custom ? t('avatar_own') : t('avatar_initials')}</strong>
                    <p>{allowed ? t('avatar_hint') : t('avatar_disabled')}</p>
                    <div className={'techtyl-account-actions'}>
                        {allowed && (
                            <Button variant={'primary'} icon={<CameraIcon aria-hidden />} onClick={() => input.current?.click()}>
                                {account.avatar.custom ? t('avatar_replace') : t('avatar_upload')}
                            </Button>
                        )}
                        {account.avatar.custom && (
                            <Button variant={'quiet'} icon={<TrashIcon aria-hidden />} busy={removing} onClick={remove}>
                                {t('avatar_remove')}
                            </Button>
                        )}
                        <Saved show={saved} />
                    </div>
                </div>
                <input
                    ref={input}
                    type={'file'}
                    accept={'image/png,image/jpeg,image/webp'}
                    className={'techtyl-account-sr'}
                    tabIndex={-1}
                    aria-hidden
                    onChange={(event) => {
                        pick(event.currentTarget.files?.[0]);
                        event.currentTarget.value = '';
                    }}
                />
            </div>
            {error && <Notice tone={'bad'}>{error}</Notice>}
            <CropDialog
                source={source}
                onClose={() => setSource(null)}
                onDone={() => {
                    setSource(null);
                    flashSaved();
                }}
            />
        </div>
    );
}

const CropDialog = ({ source, onClose, onDone }: { source: { url: string; image: HTMLImageElement } | null; onClose: () => void; onDone: () => void }) => {
    const t = useT(texts);
    const { account } = useAccount();
    const frame = useRef<HTMLDivElement>(null);
    const drag = useRef<{ x: number; y: number; startX: number; startY: number } | null>(null);
    const [side, setSide] = useState(280);
    const [view, setView] = useState<View>({ zoom: 1, x: 0, y: 0 });
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);

    const image = source?.image;
    const width = image?.naturalWidth || 1;
    const height = image?.naturalHeight || 1;
    const base = side / Math.min(width, height);
    const scale = base * view.zoom;

    const clamp = (next: View): View => {
        const zoom = Math.max(1, Math.min(MAX_ZOOM, next.zoom));
        const s = base * zoom;

        return {
            zoom,
            x: Math.min(0, Math.max(side - width * s, next.x)),
            y: Math.min(0, Math.max(side - height * s, next.y)),
        };
    };

    const center = () => {
        const s = base;
        setView({ zoom: 1, x: (side - width * s) / 2, y: (side - height * s) / 2 });
    };

    // The frame only exists once the dialog has rendered; measure it through a callback ref and
    // keep measuring while it lives (narrow screens, rotation).
    const observer = useRef<ResizeObserver | null>(null);
    const frameRef = useCallback((element: HTMLDivElement | null) => {
        (frame as React.MutableRefObject<HTMLDivElement | null>).current = element;
        observer.current?.disconnect();
        observer.current = null;
        if (!element) return;
        const measure = () => element.clientWidth > 0 && setSide(element.clientWidth);
        measure();
        if (typeof ResizeObserver !== 'undefined') {
            observer.current = new ResizeObserver(measure);
            observer.current.observe(element);
        }
    }, []);

    useLayoutEffect(() => {
        if (!source) return undefined;
        setError(null);
        setProgress(0);

        return undefined;
    }, [source]);

    useEffect(center, [source, side]);

    const zoomTo = (zoom: number) => {
        // Keep the middle of the frame where it is.
        const cx = (side / 2 - view.x) / scale;
        const cy = (side / 2 - view.y) / scale;
        const next = base * Math.max(1, Math.min(MAX_ZOOM, zoom));
        setView(clamp({ zoom, x: side / 2 - cx * next, y: side / 2 - cy * next }));
    };

    const onKey = (event: React.KeyboardEvent) => {
        const step = event.shiftKey ? 30 : 8;
        const moves: Record<string, [number, number]> = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
        if (moves[event.key]) {
            event.preventDefault();
            setView(clamp({ ...view, x: view.x + moves[event.key][0], y: view.y + moves[event.key][1] }));
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            zoomTo(view.zoom + 0.2);
        } else if (event.key === '-') {
            event.preventDefault();
            zoomTo(view.zoom - 0.2);
        }
    };

    const save = async () => {
        if (!image || !account) return;
        setBusy(true);
        setError(null);
        try {
            const sourceX = -view.x / scale;
            const sourceY = -view.y / scale;
            const sourceSide = side / scale;
            let blob: Blob | null = null;
            for (const size of OUTPUT_SIZES) {
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('canvas');
                context.imageSmoothingQuality = 'high';
                context.drawImage(image, sourceX, sourceY, sourceSide, sourceSide, 0, 0, size, size);
                blob = await toBlob(canvas, 'image/png');
                if (blob && blob.size <= account.limits.maxUploadBytes) break;
                // Too large as PNG: try a compressed format the server can read.
                for (const type of ['image/webp', 'image/jpeg']) {
                    if (!account.limits.mimeTypes.includes(type)) continue;
                    const smaller = await toBlob(canvas, type, 0.86);
                    if (smaller && smaller.type === type && smaller.size <= account.limits.maxUploadBytes) {
                        blob = smaller;
                        break;
                    }
                }
                if (blob && blob.size <= account.limits.maxUploadBytes) break;
            }
            if (!blob || blob.size > account.limits.maxUploadBytes) {
                setError(t('avatar_err_size', { size: Math.round(account.limits.maxUploadBytes / 1024) }));
                setBusy(false);

                return;
            }
            const avatar = await uploadAvatar(blob, setProgress);
            patchAccount({ avatar });
            setBusy(false);
            onDone();
        } catch (e) {
            setError(explain(t, e));
            setBusy(false);
        }
    };

    return (
        <Modal
            open={!!source}
            onClose={onClose}
            locked={busy}
            title={t('crop_title')}
            description={t('crop_text')}
            footer={
                <>
                    <Button variant={'quiet'} onClick={onClose} disabled={busy}>
                        {t('cancel')}
                    </Button>
                    <Button variant={'primary'} onClick={save} busy={busy}>
                        {busy && progress > 0 && progress < 100 ? t('uploading', { percent: progress }) : t('crop_save')}
                    </Button>
                </>
            }
        >
            <div className={'techtyl-account-crop'}>
                <div
                    ref={frameRef}
                    className={'techtyl-account-crop-frame'}
                    tabIndex={0}
                    role={'application'}
                    aria-label={t('crop_frame')}
                    aria-describedby={'techtyl-account-crop-help'}
                    onKeyDown={onKey}
                    onPointerDown={(event) => {
                        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
                        drag.current = { x: event.clientX, y: event.clientY, startX: view.x, startY: view.y };
                    }}
                    onPointerMove={(event) => {
                        if (!drag.current) return;
                        const d = drag.current;
                        setView(clamp({ ...view, x: d.startX + event.clientX - d.x, y: d.startY + event.clientY - d.y }));
                    }}
                    onPointerUp={() => (drag.current = null)}
                    onPointerCancel={() => (drag.current = null)}
                    onWheel={(event) => zoomTo(view.zoom * (event.deltaY < 0 ? 1.08 : 1 / 1.08))}
                    data-autofocus
                >
                    {source && (
                        <img
                            src={source.url}
                            alt={''}
                            draggable={false}
                            style={{ width: width * scale, height: height * scale, transform: `translate(${view.x}px, ${view.y}px)` }}
                        />
                    )}
                    <span className={'techtyl-account-crop-mask'} aria-hidden />
                </div>
                <div className={'techtyl-account-crop-tools'}>
                    <button type={'button'} onClick={() => zoomTo(view.zoom - 0.25)} aria-label={t('zoom_out')} disabled={view.zoom <= 1}>
                        <ZoomOutIcon aria-hidden />
                    </button>
                    <input
                        type={'range'}
                        min={1}
                        max={MAX_ZOOM}
                        step={0.01}
                        value={view.zoom}
                        aria-label={t('zoom')}
                        aria-valuetext={`${Math.round(view.zoom * 100)} %`}
                        onChange={(event) => zoomTo(Number(event.currentTarget.value))}
                    />
                    <button type={'button'} onClick={() => zoomTo(view.zoom + 0.25)} aria-label={t('zoom_in')} disabled={view.zoom >= MAX_ZOOM}>
                        <ZoomInIcon aria-hidden />
                    </button>
                    <span className={'techtyl-account-crop-percent'}>{Math.round(view.zoom * 100)}&nbsp;%</span>
                </div>
                <p id={'techtyl-account-crop-help'} className={'techtyl-account-small'}>
                    <PhotographIcon aria-hidden /> {t('crop_help')}
                </p>
                {error && <Notice tone={'bad'}>{error}</Notice>}
            </div>
        </Modal>
    );
};
