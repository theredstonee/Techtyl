import React from 'react';

// Own line icons on a 24 grid (same drawings as the admin designer). Only constant path data,
// never user input.
const PATHS: Record<string, string> = {
    megafon: 'M3 10v4a1 1 0 0 0 1 1h2l5 4V5L6 9H4a1 1 0 0 0-1 1z M15 9a4 4 0 0 1 0 6 M18 6a8 8 0 0 1 0 12',
    info: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 11v5 M12 7.5h.01',
    stern: 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.6l1-5.8L3.5 9.7l5.9-.9z',
    rakete: 'M9 12c1-5 5-8 11-8 0 6-3 10-8 11z M9 12H5l3-4h4 M12 15v4l4-3v-4 M6 18c-1 1-1 3-1 3s2 0 3-1 M14.5 9.5h.01',
    werkzeug: 'M15 4a5 5 0 0 0-4.6 6.9L4 17.3V20h2.7l6.4-6.4A5 5 0 0 0 20 9l-3 3-3-3 3-3a5 5 0 0 0-2-2z',
    warnung: 'M12 4l9 16H3z M12 10v4 M12 17h.01',
    herz: 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z',
    kalender: 'M4 6h16v14H4z M4 10h16 M8 3v4 M16 3v4',
    glocke: 'M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z M10 20a2 2 0 0 0 4 0',
    geschenk: 'M4 10h16v10H4z M3 7h18v3H3z M12 7v13 M12 7c-2-4-6-3-5 0 M12 7c2-4 6-3 5 0',
    support:
        'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 8a4 4 0 1 0 0 8a4 4 0 0 0 0-8z M5.6 5.6l3.6 3.6 M14.8 14.8l3.6 3.6 M18.4 5.6l-3.6 3.6 M9.2 14.8l-3.6 3.6',
    status: 'M3 12h4l2-5 4 10 2-5h6',
    billing: 'M3 6h18v12H3z M3 10h18 M7 15h4',
    docs: 'M5 4h9a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z M5 17a3 3 0 0 1 3-3h9',
    website: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M3 12h18 M12 3c3 3 3 15 0 18 M12 3c-3 3-3 15 0 18',
    discord:
        'M8 6.5c2.6-1 5.4-1 8 0l2 9.5c-1.6 1.4-3.3 2-4.6 2l-.9-1.6 M8 6.5L6 16c1.6 1.4 3.3 2 4.6 2l.9-1.6 M9.6 12.2h.01 M14.4 12.2h.01 M8.5 15.2c2.3.9 4.7.9 7 0',
    github: 'M8 8l-4 4 4 4 M16 8l4 4-4 4 M13.5 6l-3 12',
    youtube: 'M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z M10 9l5 3-5 3z',
    twitch: 'M5 4h15v10l-4 4h-4l-3 3v-3H5z M11 8v4 M15 8v4',
    x: 'M5 5l14 14 M19 5L5 19',
    instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z M12 9a3 3 0 1 0 0 6a3 3 0 0 0 0-6z M17 7h.01',
    tiktok: 'M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5 M14 4c.5 2.5 2.5 4 5 4',
    mastodon: 'M16 12a4 4 0 1 1-1.2-2.8 M16 9v4.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1',
    reddit: 'M12 8c4.4 0 8 2.5 8 5.5S16.4 19 12 19s-8-2.5-8-5.5S7.6 8 12 8z M12 8l1.5-4.5 4 1 M9 13h.01 M15 13h.01 M9.5 16c1.5 1 3.5 1 5 0',
    close: 'M6 6l12 12 M18 6L6 18',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    upload: 'M12 16V4 M7 9l5-5 5 5 M4 16v4h16v-4',
    trash: 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13',
    lock: 'M6 11h12v9H6z M8.5 11V8a3.5 3.5 0 0 1 7 0v3',
    undo: 'M9 14L4 9l5-5 M4 9h10a6 6 0 0 1 0 12h-3',
    external: 'M14 4h6v6 M20 4l-9 9 M18 14v6H4V6h6',
};

export const hasIcon = (name: string): boolean => Object.prototype.hasOwnProperty.call(PATHS, name);

export default function Icon({ name, className }: { name: string; className?: string }) {
    const d = hasIcon(name) ? PATHS[name] : PATHS.info;
    return (
        <svg
            className={'techtyl-designer-icon' + (className ? ' ' + className : '')}
            viewBox={'0 0 24 24'}
            aria-hidden
            focusable={'false'}
        >
            {d.split(' M').map((part, index) => (
                <path key={index} d={(index === 0 ? '' : 'M') + part} />
            ))}
        </svg>
    );
}
