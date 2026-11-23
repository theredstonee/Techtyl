import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/designer';
import Icon from './icons';
import Markdown from './markdown';
import { Announcement } from './bootstrap';

const STORAGE_KEY = 'techtyl-announcement-dismissed';

const readDismissed = (): string => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (error) {
        return '';
    }
};

const inPreview = (): boolean => document.documentElement.getAttribute('data-techtyl-preview') === 'an';

/** Announcement from the designer: card above the content, slim strip or floating card. */
export default function AnnouncementBar({ announcement, raised }: { announcement: Announcement; raised: boolean }) {
    const t = useT(texts);
    const [state, setState] = useState<'shown' | 'leaving' | 'hidden'>(() =>
        announcement.dismissible && !inPreview() && readDismissed() === announcement.revision ? 'hidden' : 'shown'
    );

    // New content (another revision) is shown again.
    useEffect(() => {
        setState(announcement.dismissible && !inPreview() && readDismissed() === announcement.revision ? 'hidden' : 'shown');
    }, [announcement.revision, announcement.dismissible]);

    if (state === 'hidden') return null;

    const dismiss = () => {
        if (!inPreview()) {
            try {
                window.localStorage.setItem(STORAGE_KEY, announcement.revision);
            } catch (error) {
                /* without storage it only stays hidden on this page */
            }
        }
        setState('leaving');
        window.setTimeout(() => setState('hidden'), 260);
    };

    const box = (
        <div
            className={
                'techtyl-designer-announce is-' +
                announcement.position +
                (state === 'leaving' ? ' is-leaving' : '') +
                (raised ? ' is-raised' : '')
            }
            data-kind={announcement.kind}
            role={'status'}
        >
            <span className={'techtyl-designer-announce-icon'}>
                <Icon name={announcement.icon} />
            </span>
            <div className={'techtyl-designer-announce-text'}>
                <Markdown text={announcement.text} />
            </div>
            {announcement.dismissible && (
                <button type={'button'} className={'techtyl-designer-announce-close'} onClick={dismiss} aria-label={t('dismiss')} title={t('dismiss')}>
                    <Icon name={'close'} />
                </button>
            )}
        </div>
    );

    return announcement.position === 'schwebend' ? createPortal(box, document.body) : box;
}
