import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/assistant';

type Translate = (key: string, replace?: Record<string, string | number>) => string;

/*
 * Kleine, strikt escapende Markdown-Darstellung fuer Antworten des Modells. Die Antwort ist nicht
 * vertrauenswuerdig: Es entsteht nie HTML aus dem Text, alles laeuft als Text durch React.
 * Unterstuetzt: Absaetze, Listen, Code-Bloecke, `Code`, **fett**, *kursiv*, Ueberschriften, Links.
 * Bilder werden nie geladen; Links gibt es nur fuer http/https und fuer bekannte Panel-Seiten.
 */

type Block =
    | { kind: 'code'; lang: string; text: string }
    | { kind: 'list'; ordered: boolean; start: number; items: string[] }
    | { kind: 'heading'; text: string }
    | { kind: 'quote'; text: string }
    | { kind: 'rule' }
    | { kind: 'paragraph'; text: string };

const FENCE = /^\s{0,3}(```+|~~~+)\s*([A-Za-z0-9_+.#-]{0,24})\s*$/;
const BULLET = /^\s{0,8}[-*•]\s+(.*)$/;
const NUMBER = /^\s{0,8}(\d{1,4})[.)]\s+(.*)$/;
const HEADING = /^\s{0,3}#{1,6}\s+(.*?)\s*#*\s*$/;
const RULE = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/;
const QUOTE = /^\s{0,3}>\s?(.*)$/;

const parseBlocks = (source: string): Block[] => {
    const lines = source.replace(/\r\n?/g, '\n').split('\n');
    const blocks: Block[] = [];
    let paragraph: string[] = [];

    const flush = () => {
        if (paragraph.length) {
            blocks.push({ kind: 'paragraph', text: paragraph.join('\n') });
            paragraph = [];
        }
    };

    let i = 0;
    while (i < lines.length) {
        const line = lines[i];
        const fence = line.match(FENCE);
        if (fence) {
            flush();
            const marker = fence[1].charAt(0);
            const code: string[] = [];
            i++;
            // Ein offener Block (noch im Stream) reicht bis zum Ende des Textes.
            while (i < lines.length && !(lines[i].trim().charAt(0) === marker && /^\s{0,3}(```+|~~~+)\s*$/.test(lines[i]))) {
                code.push(lines[i]);
                i++;
            }
            i++;
            blocks.push({ kind: 'code', lang: fence[2] || '', text: code.join('\n') });
            continue;
        }
        if (line.trim() === '') {
            flush();
            i++;
            continue;
        }
        if (RULE.test(line)) {
            flush();
            blocks.push({ kind: 'rule' });
            i++;
            continue;
        }
        const heading = line.match(HEADING);
        if (heading) {
            flush();
            blocks.push({ kind: 'heading', text: heading[1] });
            i++;
            continue;
        }
        const bullet = line.match(BULLET);
        const number = bullet ? null : line.match(NUMBER);
        if (bullet || number) {
            flush();
            const ordered = !!number;
            const items: string[] = [];
            const start = number ? parseInt(number[1], 10) : 1;
            while (i < lines.length) {
                const current = lines[i];
                const item = ordered ? current.match(NUMBER) : current.match(BULLET);
                if (item) {
                    items.push(ordered ? item[2] : item[1]);
                } else if (current.trim() !== '' && /^\s{2,}\S/.test(current) && !FENCE.test(current) && items.length) {
                    // Eingerueckte Folgezeile gehoert zum Punkt davor.
                    items[items.length - 1] += '\n' + current.trim();
                } else {
                    break;
                }
                i++;
            }
            blocks.push({ kind: 'list', ordered, start, items });
            continue;
        }
        const quote = line.match(QUOTE);
        if (quote) {
            flush();
            const rows: string[] = [];
            while (i < lines.length) {
                const row = lines[i].match(QUOTE);
                if (!row) break;
                rows.push(row[1]);
                i++;
            }
            blocks.push({ kind: 'quote', text: rows.join('\n') });
            continue;
        }
        paragraph.push(line);
        i++;
    }
    flush();

    return blocks;
};

type Href = { kind: 'external' | 'internal'; value: string } | null;

/** Erlaubt sind nur http(s)-Adressen ohne Zugangsdaten und feste Pfade im Panel. */
export const safeHref = (raw: string): Href => {
    const value = raw.trim();
    if (/^\/(server\/[a-f0-9]{8}(\/[a-z]{1,24})?|account(\/[a-z]{1,24})?)$/.test(value)) {
        return { kind: 'internal', value };
    }
    if (!/^https?:\/\//i.test(value) || value.length > 2000) {
        return null;
    }
    try {
        const url = new URL(value);
        if ((url.protocol !== 'https:' && url.protocol !== 'http:') || url.username || url.password) {
            return null;
        }

        return { kind: 'external', value: url.href };
    } catch (e) {
        return null;
    }
};

// 1-2: `Code`, 3-4: Bild, 5-6: Link, 7: fett, 8: kursiv, 9: blanke Adresse
const INLINE =
    /(`+)([^`\n]|[^`\n][^\n]*?[^`\n])\1(?!`)|!\[([^\]\n]*)\]\(([^)\s]+)(?:\s+"[^"\n]*")?\)|\[([^\]\n]+)\]\(([^)\s]+)(?:\s+"[^"\n]*")?\)|\*\*([^\s*](?:[^\n]*?[^\s*])?)\*\*|\*([^\s*](?:[^*\n]*[^\s*])?)\*|(https?:\/\/[^\s<>"'`\])]+)/g;

const renderLink = (label: React.ReactNode, raw: string, key: string): React.ReactNode => {
    const href = safeHref(raw);
    if (!href) {
        // Unerlaubte Ziele (z. B. javascript:) bleiben sichtbarer Text.
        return (
            <span key={key}>
                {label} ({raw})
            </span>
        );
    }
    if (href.kind === 'internal') {
        return (
            <Link key={key} to={href.value} className={'techtyl-assistant-link'}>
                {label}
            </Link>
        );
    }

    return (
        <a key={key} href={href.value} target={'_blank'} rel={'noopener noreferrer'} className={'techtyl-assistant-link'}>
            {label}
        </a>
    );
};

const renderInline = (text: string, prefix: string, t: Translate, depth = 0): React.ReactNode[] => {
    const out: React.ReactNode[] = [];
    const pattern = new RegExp(INLINE.source, 'g');
    let last = 0;
    let count = 0;
    let match = pattern.exec(text);

    const plain = (value: string) => {
        if (!value) return;
        value.split('\n').forEach((part, index) => {
            if (index > 0) out.push(<br key={`${prefix}-b${count++}`} />);
            if (part) out.push(part);
        });
    };

    while (match) {
        plain(text.slice(last, match.index));
        const key = `${prefix}-${count++}`;
        if (match[1]) {
            out.push(
                <code key={key} className={'techtyl-assistant-code'}>
                    {match[2]}
                </code>
            );
        } else if (match[4] !== undefined && match[3] !== undefined) {
            // Bilder werden nie geladen (kein Nachladen fremder Adressen, kein Zaehlpixel).
            out.push(
                <span key={key} className={'techtyl-assistant-noimage'}>
                    {t('image_blocked', { alt: match[3] ? `: ${match[3]}` : '' })}
                </span>
            );
        } else if (match[6] !== undefined) {
            out.push(renderLink(match[5], match[6], key));
        } else if (match[7] !== undefined) {
            out.push(<strong key={key}>{depth < 3 ? renderInline(match[7], key, t, depth + 1) : match[7]}</strong>);
        } else if (match[8] !== undefined) {
            out.push(<em key={key}>{depth < 3 ? renderInline(match[8], key, t, depth + 1) : match[8]}</em>);
        } else if (match[9] !== undefined) {
            // Satzzeichen am Ende gehoeren nicht zur Adresse.
            const trimmed = match[9].replace(/[.,;:!?]+$/, '');
            out.push(renderLink(trimmed, trimmed, key));
            plain(match[9].slice(trimmed.length));
        }
        last = match.index + match[0].length;
        match = pattern.exec(text);
    }
    plain(text.slice(last));

    return out;
};

const copyText = async (text: string): Promise<boolean> => {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch (e) {
        // Weiter mit dem aelteren Weg.
    }
    try {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const done = document.execCommand('copy');
        document.body.removeChild(area);

        return done;
    } catch (e) {
        return false;
    }
};

const CodeBlock = ({ lang, text }: { lang: string; text: string }) => {
    const t = useT(texts);
    const [state, setState] = useState<'idle' | 'done' | 'failed'>('idle');
    const timer = useRef<number>();

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const copy = () => {
        copyText(text).then((done) => {
            setState(done ? 'done' : 'failed');
            window.clearTimeout(timer.current);
            timer.current = window.setTimeout(() => setState('idle'), 1800);
        });
    };

    return (
        <div className={'techtyl-assistant-block'}>
            <div className={'techtyl-assistant-block-head'}>
                <span>{lang || t('code')}</span>
                <button type={'button'} onClick={copy} className={'techtyl-assistant-copy'} data-state={state}>
                    {state === 'done' ? t('copied') : state === 'failed' ? t('copy_failed') : t('copy')}
                </button>
            </div>
            <pre>
                <code>{text}</code>
            </pre>
        </div>
    );
};

const Markdown = ({ source }: { source: string }) => {
    const t = useT(texts);
    const blocks = parseBlocks(source);

    return (
        <div className={'techtyl-assistant-md'}>
            {blocks.map((block, index) => {
                const key = `b${index}`;
                switch (block.kind) {
                    case 'code':
                        return <CodeBlock key={key} lang={block.lang} text={block.text} />;
                    case 'list': {
                        const items = block.items.map((item, n) => <li key={`${key}-${n}`}>{renderInline(item, `${key}-${n}`, t)}</li>);

                        return block.ordered ? (
                            <ol key={key} start={block.start}>
                                {items}
                            </ol>
                        ) : (
                            <ul key={key}>{items}</ul>
                        );
                    }
                    case 'heading':
                        return (
                            <p key={key} className={'techtyl-assistant-heading'}>
                                {renderInline(block.text, key, t)}
                            </p>
                        );
                    case 'quote':
                        return (
                            <blockquote key={key} className={'techtyl-assistant-quote'}>
                                {renderInline(block.text, key, t)}
                            </blockquote>
                        );
                    case 'rule':
                        return <hr key={key} />;
                    default:
                        return <p key={key}>{renderInline(block.text, key, t)}</p>;
                }
            })}
        </div>
    );
};

export default React.memo(Markdown);
