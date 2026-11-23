import React from 'react';
import { createPortal } from 'react-dom';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/designer';
import Icon from './icons';
import { LinkSet } from './bootstrap';

const NETWORKS: Record<string, string> = {
    discord: 'Discord',
    github: 'GitHub',
    youtube: 'YouTube',
    twitch: 'Twitch',
    x: 'X',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    mastodon: 'Mastodon',
    reddit: 'Reddit',
};

/** Website and social links from the designer, as a bar above the content or a floating dock. */
export default function LinkBar({ links }: { links: LinkSet }) {
    const t = useT(texts);
    const newTab = t('opens_new_tab');

    const bar = (
        <nav className={'techtyl-designer-links is-' + links.position + ' is-' + links.align} aria-label={t('links_label')}>
            {links.website.length > 0 && (
                <ul className={'techtyl-designer-links-site'}>
                    {links.website.map((link, index) => {
                        const label = link.label || t('link_' + link.kind);
                        return (
                            <li key={index}>
                                <a href={link.url} target={'_blank'} rel={'noopener noreferrer'} title={label + ' (' + newTab + ')'}>
                                    <Icon name={link.kind} />
                                    <span>{label}</span>
                                </a>
                            </li>
                        );
                    })}
                </ul>
            )}
            {links.social.length > 0 && (
                <ul className={'techtyl-designer-links-social'}>
                    {links.social.map((link, index) => (
                        <li key={index}>
                            <a href={link.url} target={'_blank'} rel={'noopener noreferrer'} title={NETWORKS[link.network] + ' (' + newTab + ')'}>
                                <Icon name={link.network} />
                                <span className={'techtyl-designer-sr'}>{NETWORKS[link.network]}</span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </nav>
    );

    return links.position === 'schwebend' ? createPortal(bar, document.body) : bar;
}
