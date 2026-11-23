import React, { useEffect, useState } from 'react';
import AnnouncementBar from '../designer/AnnouncementBar';
import LinkBar from '../designer/LinkBar';
import { Announcement, LinkSet, cleanAnnouncement, cleanLinks, loadBootstrap } from '../designer/bootstrap';
import '../designer/styles.css';

interface Content {
    announcement: Announcement | null;
    links: LinkSet | null;
}

// Live data from the admin designer while this page runs as its preview (set by receiver.js).
const previewContent = (): Content | null => {
    const detail = (window as any).__techtylDesignerPreview;
    return detail ? { announcement: cleanAnnouncement(detail.announcement), links: cleanLinks(detail.links) } : null;
};

/**
 * Mounted in Dashboard.Global.BeforeSection (above the content of every page): the announcement
 * and the links from the designer. Data comes from the bootstrap request (once per page load).
 */
export default () => {
    const [content, setContent] = useState<Content | null>(previewContent);

    useEffect(() => {
        let active = true;
        let previewed = previewContent() !== null;
        loadBootstrap()
            .then((data) => {
                if (active && !previewed) setContent({ announcement: data.announcement, links: data.links });
            })
            .catch(() => {
                /* without data there is simply nothing to show */
            });
        const onPreview = (event: Event) => {
            previewed = true;
            const detail = (event as CustomEvent).detail || {};
            setContent({ announcement: cleanAnnouncement(detail.announcement), links: cleanLinks(detail.links) });
        };
        window.addEventListener('techtyl:designer-preview', onPreview);
        return () => {
            active = false;
            window.removeEventListener('techtyl:designer-preview', onPreview);
        };
    }, []);

    if (!content || (!content.announcement && !content.links)) return null;
    const { announcement, links } = content;
    const inline = (announcement && announcement.position !== 'schwebend') || (links && links.position === 'oben');
    const dockRight = !!links && links.position === 'schwebend' && links.align === 'rechts';

    return (
        <>
            {inline && (
                <div className={'techtyl-designer-shell'}>
                    {announcement && announcement.position === 'leiste' && <AnnouncementBar announcement={announcement} raised={false} />}
                    {links && links.position === 'oben' && <LinkBar links={links} />}
                    {announcement && announcement.position === 'karte' && <AnnouncementBar announcement={announcement} raised={false} />}
                </div>
            )}
            {announcement && announcement.position === 'schwebend' && <AnnouncementBar announcement={announcement} raised={dockRight} />}
            {links && links.position === 'schwebend' && <LinkBar links={links} />}
        </>
    );
};
