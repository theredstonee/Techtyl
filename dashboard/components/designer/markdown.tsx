import React from 'react';

// Minimal, strictly escaping Markdown for announcements: **bold**, *italic* / _italic_ and
// [text](https://...). Everything else stays plain text; React escapes all text. Links are only
// rendered for http/https and always open with rel="noopener noreferrer". No HTML is produced
// from strings, there is no dangerouslySetInnerHTML.

const PATTERN = /\*\*([^*\n]+?)\*\*|\*([^*\n]+?)\*|_([^_\n]+?)_|\[([^\]\n]{1,120})\]\(([^)\s]{1,300})\)/g;

export const isHttpUrl = (value: unknown): value is string => {
    if (typeof value !== 'string') return false;
    const url = value.trim();
    if (!url || url.length > 300 || /[\s"'<>\\`]/.test(url)) return false;
    try {
        const parsed = new URL(url);
        return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && !parsed.username && !parsed.password && !!parsed.hostname;
    } catch (error) {
        return false;
    }
};

const inline = (text: string, depth: number, keyPrefix: string): React.ReactNode[] => {
    const nodes: React.ReactNode[] = [];
    const pattern = new RegExp(PATTERN.source, 'g');
    let last = 0;
    let match: RegExpExecArray | null;
    let index = 0;
    while ((match = pattern.exec(text)) !== null) {
        const key = keyPrefix + '-' + index++;
        if (match.index > last) nodes.push(text.slice(last, match.index));
        if (match[1] !== undefined) {
            nodes.push(<strong key={key}>{depth < 2 ? inline(match[1], depth + 1, key) : match[1]}</strong>);
        } else if (match[2] !== undefined || match[3] !== undefined) {
            const inner = match[2] !== undefined ? match[2] : (match[3] as string);
            nodes.push(<em key={key}>{depth < 2 ? inline(inner, depth + 1, key) : inner}</em>);
        } else if (isHttpUrl(match[5])) {
            nodes.push(
                <a key={key} href={match[5]} target={'_blank'} rel={'noopener noreferrer'}>
                    {match[4]}
                </a>
            );
        } else {
            nodes.push(match[0]);
        }
        last = pattern.lastIndex;
    }
    if (last < text.length) nodes.push(text.slice(last));
    return nodes;
};

export default function Markdown({ text }: { text: string }) {
    const lines = String(text || '').split('\n');
    return (
        <>
            {lines.map((line, index) => (
                <React.Fragment key={index}>
                    {index > 0 && <br />}
                    {inline(line, 0, 'l' + index)}
                </React.Fragment>
            ))}
        </>
    );
}
