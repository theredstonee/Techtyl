import React from 'react';
import { tt } from './text';

/*
 * Beschreibungen von Modrinth sind Markdown mit eingestreutem HTML und stammen von Fremden.
 * Hier entsteht daraus ausschliesslich Text in React-Elementen: HTML-Tags werden vorher in
 * Markdown uebersetzt oder verworfen, es gibt kein dangerouslySetInnerHTML, Links nur mit
 * http/https, Bilder nur von cdn.modrinth.com.
 */

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', '#39': "'" };

const decode = (text: string): string =>
    text.replace(/&(amp|lt|gt|quot|apos|nbsp|#39);/g, (_, key: string) => ENTITIES[key] || '').replace(/&#(\d{2,5});/g, (_, code: string) => {
        const value = parseInt(code, 10);

        return value >= 32 && value < 65536 ? String.fromCharCode(value) : '';
    });

const attribute = (tag: string, key: string): string => {
    const match = new RegExp(`${key}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i').exec(tag);

    return match ? match[1] || match[2] || match[3] || '' : '';
};

/** Uebersetzt die ueblichen HTML-Tags in Markdown und entfernt alle uebrigen. */
const htmlToMarkdown = (source: string): string => {
    let text = source.slice(0, 60000).replace(/\r\n?/g, '\n');
    text = text.replace(/<!--[\s\S]*?-->/g, '');
    text = text.replace(/<(script|style|iframe|object|svg|video|audio)\b[\s\S]*?<\/\1\s*>/gi, '');
    text = text.replace(/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1\s*>/gi, (_, level: string, inner: string) => `\n\n${'#'.repeat(Number(level))} ${inner.replace(/\s+/g, ' ').trim()}\n\n`);
    text = text.replace(/<a\b([^>]*)>([\s\S]*?)<\/a\s*>/gi, (_, attrs: string, inner: string) => {
        const href = attribute(attrs, 'href');
        const label = inner.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();

        return label && href ? `[${label.replace(/[[\]]/g, '')}](${href.replace(/[()\s]/g, '')})` : label;
    });
    text = text.replace(/<img\b([^>]*)>/gi, (_, attrs: string) => {
        const src = attribute(attrs, 'src');

        return src ? `![${attribute(attrs, 'alt').replace(/[[\]]/g, '')}](${src.replace(/[()\s]/g, '')})` : '';
    });
    text = text.replace(/<(strong|b)\b[^>]*>([\s\S]*?)<\/\1\s*>/gi, '**$2**');
    text = text.replace(/<(em|i)\b[^>]*>([\s\S]*?)<\/\1\s*>/gi, '*$2*');
    text = text.replace(/<code\b[^>]*>([\s\S]*?)<\/code\s*>/gi, '`$1`');
    text = text.replace(/<li\b[^>]*>/gi, '\n- ');
    text = text.replace(/<br\s*\/?>/gi, '\n');
    text = text.replace(/<hr\s*\/?>/gi, '\n\n---\n\n');
    text = text.replace(/<\/(p|div|ul|ol|li|table|tr|blockquote|center|details|summary|pre)\s*>/gi, '\n\n');
    text = text.replace(/<\/?[A-Za-z][^>]*>/g, '');

    return decode(text);
};

const safeLink = (url: string): string | null => (/^https?:\/\/[^\s<>"']+$/i.test(url) && url.length <= 500 ? url : null);
const safeImage = (url: string): string | null => (/^https:\/\/cdn\.modrinth\.com\/[^\s<>"']+$/i.test(url) && url.length <= 300 ? url : null);

/** Inline-Markdown: Code, fett, kursiv, Links, Bilder. Alles andere bleibt Text. */
const inline = (text: string, keyPrefix: string): React.ReactNode[] => {
    const nodes: React.ReactNode[] = [];
    const pattern = /(`[^`\n]+`)|(!\[[^\]\n]*\]\([^)\s]+\))|(\[[^\]\n]+\]\([^)\s]+\))|(\*\*[^*\n]+\*\*)|(__[^_\n]+__)|(\*[^*\n]+\*)|(https?:\/\/[^\s<>)\]]+)/g;
    let last = 0;
    let index = 0;
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(text)) !== null && index < 400) {
        if (match.index > last) nodes.push(text.slice(last, match.index));
        const token = match[0];
        const key = `${keyPrefix}-${index++}`;
        if (match[1]) {
            nodes.push(<code key={key}>{token.slice(1, -1)}</code>);
        } else if (match[2]) {
            const parts = /^!\[([^\]]*)\]\(([^)]+)\)$/.exec(token);
            const src = parts ? safeImage(parts[2]) : null;
            if (src) {
                nodes.push(<img key={key} src={src} alt={parts ? parts[1] : ''} loading={'lazy'} referrerPolicy={'no-referrer'} />);
            } else if (parts && parts[1]) {
                nodes.push(
                    <span key={key} className={'techtyl-tools-md-image'}>
                        {tt('image_alt')} {parts[1]}
                    </span>
                );
            }
        } else if (match[3]) {
            const parts = /^\[([^\]]+)\]\(([^)]+)\)$/.exec(token);
            const href = parts ? safeLink(parts[2]) : null;
            nodes.push(
                href ? (
                    <a key={key} href={href} target={'_blank'} rel={'noopener noreferrer nofollow'}>
                        {parts![1]}
                    </a>
                ) : (
                    parts ? parts[1] : token
                )
            );
        } else if (match[4] || match[5]) {
            nodes.push(<strong key={key}>{token.slice(2, -2)}</strong>);
        } else if (match[6]) {
            nodes.push(<em key={key}>{token.slice(1, -1)}</em>);
        } else {
            const href = safeLink(token);
            nodes.push(
                href ? (
                    <a key={key} href={href} target={'_blank'} rel={'noopener noreferrer nofollow'}>
                        {token}
                    </a>
                ) : (
                    token
                )
            );
        }
        last = match.index + token.length;
    }
    if (last < text.length) nodes.push(text.slice(last));

    return nodes;
};

export const SafeMarkdown = ({ source }: { source: string }) => {
    const lines = htmlToMarkdown(source).split('\n');
    const blocks: React.ReactNode[] = [];
    let paragraph: string[] = [];
    let list: { ordered: boolean; items: string[] } | null = null;
    let code: string[] | null = null;
    let key = 0;

    const flush = () => {
        if (paragraph.length > 0) {
            const text = paragraph.join(' ');
            blocks.push(<p key={key++}>{inline(text, `p${key}`)}</p>);
            paragraph = [];
        }
        if (list) {
            const items = list.items.map((item, index) => <li key={index}>{inline(item, `l${key}-${index}`)}</li>);
            blocks.push(list.ordered ? <ol key={key++}>{items}</ol> : <ul key={key++}>{items}</ul>);
            list = null;
        }
    };

    for (let i = 0; i < lines.length && blocks.length < 400; i++) {
        const raw = lines[i];
        if (code !== null) {
            if (/^\s*(```|~~~)/.test(raw)) {
                blocks.push(<pre key={key++}>{code.join('\n')}</pre>);
                code = null;
            } else {
                code.push(raw);
            }
            continue;
        }
        if (/^\s*(```|~~~)/.test(raw)) {
            flush();
            code = [];
            continue;
        }
        const line = raw.trim();
        if (line === '') {
            flush();
            continue;
        }
        const heading = /^(#{1,6})\s+(.*)$/.exec(line);
        if (heading) {
            flush();
            const text = heading[2].replace(/\s*#+\s*$/, '');
            blocks.push(heading[1].length <= 2 ? <h3 key={key++}>{inline(text, `h${key}`)}</h3> : <h4 key={key++}>{inline(text, `h${key}`)}</h4>);
            continue;
        }
        if (/^(-{3,}|\*{3,}|_{3,})$/.test(line)) {
            flush();
            blocks.push(<hr key={key++} />);
            continue;
        }
        const bullet = /^[-*+]\s+(.*)$/.exec(line);
        const numbered = /^\d{1,3}[.)]\s+(.*)$/.exec(line);
        if (bullet || numbered) {
            const ordered = !bullet;
            if (paragraph.length > 0 || (list && list.ordered !== ordered)) flush();
            if (!list) list = { ordered, items: [] };
            list.items.push((bullet || numbered)![1]);
            continue;
        }
        if (line.startsWith('>')) {
            flush();
            blocks.push(<blockquote key={key++}>{inline(line.replace(/^>\s?/, ''), `q${key}`)}</blockquote>);
            continue;
        }
        if (list) flush();
        // Tabellenzeilen und Reste von HTML bleiben schlichter Text.
        paragraph.push(line.replace(/^\|/, '').replace(/\|$/, '').replace(/\|/g, ' · '));
    }
    if (code !== null) blocks.push(<pre key={key++}>{code.join('\n')}</pre>);
    flush();

    return <div className={'techtyl-tools-md'}>{blocks}</div>;
};
