import { Design, Motif, SCENES, SCENE_FOR_VARIANT, imageValue, MOTION, NAVIGATION, SERVER_LAYOUTS, VARIANTS } from './bootstrap';

// Applies design values directly to <html> (CSS variables and data attributes), the same way the
// wrapper and the admin designer preview do. Every value is checked before it is used.

const root = (): HTMLElement => document.documentElement;

const inList = (value: unknown, list: readonly string[]): value is string => typeof value === 'string' && list.includes(value);
const range = (value: unknown, min: number, max: number): number | null =>
    typeof value === 'number' && isFinite(value) ? Math.max(min, Math.min(max, Math.round(value))) : null;

export const accentIsLight = (hex: string): boolean => {
    if (!/^#[0-9a-f]{6}$/i.test(hex)) return true;
    const lin = [1, 3, 5].map((at) => {
        const c = parseInt(hex.substr(at, 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2] > 0.36;
};

/** Included image of a variant (used when the background is left on "default"). */
export const variantDefault = (motifs: Motif[], variant: string): string => {
    const found = motifs.find((motif) => motif.variant === variant);
    return found ? found.url : '';
};

/** Real image address for a stored background value ('' = variant default, 'none' = no image). */
export const resolveBackground = (background: string, variant: string, motifs: Motif[]): string => {
    if (background === 'none') return '';
    if (background === '') return variantDefault(motifs, variant);
    return imageValue(background) || '';
};

/**
 * Scene written onto <html>. "auto" becomes the variant's scene, or "none" when the shown
 * image is not one of the included motifs (own upload or an external address).
 */
export const resolveScene = (scene: unknown, variant: string, backgroundUrl: string, motifs: Motif[]): string => {
    if (scene === 'none') return 'none';
    if (typeof scene === 'string' && (SCENES as readonly string[]).includes(scene)) return scene;
    if (backgroundUrl !== '' && !motifs.some((motif) => motif.url === backgroundUrl)) return 'none';
    return SCENE_FOR_VARIANT[variant] || 'contours';
};

const refreshScene = (): void => {
    const engine = (window as Window & { TechtylScene?: { refresh?: () => void } }).TechtylScene;
    if (!engine || typeof engine.refresh !== 'function') return;
    try {
        engine.refresh();
    } catch (error) {
        // The engine reports its own failures. A throw must not break the design page.
    }
};

export const applyDesign = (design: Design, backgroundUrl: string, motifs: Motif[] = []): void => {
    const html = root();
    if (inList(design.variant, VARIANTS)) html.setAttribute('data-techtyl-variant', design.variant);
    if (inList(design.navigation, NAVIGATION)) html.setAttribute('data-techtyl-nav', design.navigation);
    if (inList(design.server_layout, SERVER_LAYOUTS)) html.setAttribute('data-techtyl-servers', design.server_layout);
    if (inList(design.motion, MOTION)) html.setAttribute('data-techtyl-motion', design.motion);
    if (typeof design.banners === 'boolean') html.setAttribute('data-techtyl-banners', design.banners ? 'an' : 'aus');

    const set = (name: string, value: string | null) => {
        if (value === null) return;
        html.style.setProperty(name, value);
    };
    if (/^#[0-9a-f]{6}$/i.test(design.accent || '')) {
        const light = accentIsLight(design.accent);
        set('--techtyl-accent', design.accent.toLowerCase());
        set('--techtyl-accent-ink', light ? '#17130b' : '#ffffff');
        html.setAttribute('data-techtyl-accent', light ? 'hell' : 'dunkel');
    }
    const radius = range(design.radius, 0, 22);
    if (radius !== null) set('--techtyl-radius', radius + 'px');
    const height = range(design.banner_height, 48, 160);
    if (height !== null) set('--techtyl-banner-height', height + 'px');
    const dim = range(design.dim, 0, 90);
    if (dim !== null) set('--techtyl-dim', String(dim / 100));
    const opacity = range(design.opacity, 35, 100);
    if (opacity !== null) set('--techtyl-opacity', opacity + '%');
    const blur = range(design.blur, 0, 24);
    if (blur !== null) set('--techtyl-blur', blur + 'px');
    const image = imageValue(backgroundUrl);
    set('--techtyl-background', image ? 'url("' + image + '")' : 'none');
    const variant = inList(design.variant, VARIANTS) ? design.variant : '';
    html.setAttribute('data-techtyl-scene', resolveScene(design.scene, variant, image || '', motifs));
    refreshScene();
};
