import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import texts from '@blueprint/extensions/techtyl/i18n/serverlist';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import { errorText, placeServer, sameId } from './api';
import { setFolders, useListState } from './store';

interface FolderMenuProps {
    anchor: React.RefObject<HTMLButtonElement>;
    serverId: string;
    serverLabel: string;
    onClose: () => void;
}

// "Move to folder" for one card. The menu is portaled so the card's overflow
// does not clip it. The card link stays the click target everywhere else.
export default ({ anchor, serverId, serverLabel, onClose }: FolderMenuProps) => {
    const t = useT(texts);
    const list = useListState();
    const menu = useRef<HTMLDivElement>(null);
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState('');

    const place = () => {
        const from = anchor.current;
        const node = menu.current;
        if (!from || !node) return;
        const rect = from.getBoundingClientRect();
        const width = node.offsetWidth;
        const height = node.offsetHeight;
        let left = rect.right - width;
        if (left < 8) left = 8;
        if (left + width > window.innerWidth - 8) left = Math.max(8, window.innerWidth - width - 8);
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - 8) top = Math.max(8, rect.top - height - 6);
        node.style.top = top + 'px';
        node.style.left = left + 'px';
    };

    useLayoutEffect(() => {
        place();
        const node = menu.current;
        if (!node) return;
        const first = node.querySelector('button');
        if (first instanceof HTMLElement) first.focus();
    }, []);

    useEffect(() => {
        const onScroll = () => place();
        window.addEventListener('resize', onScroll);
        window.addEventListener('scroll', onScroll, true);
        const onDown = (event: MouseEvent) => {
            const target = event.target;
            if (!(target instanceof Node)) return;
            if (menu.current && menu.current.contains(target)) return;
            if (anchor.current && anchor.current.contains(target)) return;
            onClose();
        };
        document.addEventListener('mousedown', onDown);
        return () => {
            window.removeEventListener('resize', onScroll);
            window.removeEventListener('scroll', onScroll, true);
            document.removeEventListener('mousedown', onDown);
        };
    }, [anchor, onClose]);

    const choose = (target: number | null) => {
        if (busy) return;
        setBusy(true);
        setProblem('');
        placeServer(list.folders, serverId, target)
            .then((folders) => {
                setFolders(folders, true);
                onClose();
            })
            .catch((error: unknown) => {
                setProblem(errorText(error, t('error.generic')));
                setBusy(false);
            });
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (!menu.current) return;
        const nodes = menu.current.querySelectorAll('button');
        const items: HTMLButtonElement[] = [];
        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            if (node instanceof HTMLButtonElement && !node.disabled) items.push(node);
        }
        const index = items.indexOf(document.activeElement as HTMLButtonElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            event.nativeEvent.stopImmediatePropagation();
            onClose();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            if (items.length === 0) return;
            let next = index < 0 ? 0 : index;
            if (event.key === 'ArrowDown') next = (index + 1) % items.length;
            if (event.key === 'ArrowUp') next = (index - 1 + items.length) % items.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = items.length - 1;
            items[next].focus();
            return;
        }
        if (event.key === 'Tab') {
            event.preventDefault();
            if (items.length === 0) return;
            const edge = event.shiftKey ? 0 : items.length - 1;
            if (index === edge) items[event.shiftKey ? items.length - 1 : 0].focus();
            else items[Math.min(items.length - 1, Math.max(0, index + (event.shiftKey ? -1 : 1)))].focus();
        }
    };

    let currentId: number | null = null;
    for (let i = 0; i < list.folders.length; i++) {
        const folder = list.folders[i];
        for (let s = 0; s < folder.servers.length; s++) {
            if (sameId(folder.servers[s], serverId)) currentId = folder.id;
        }
    }

    return createPortal(
        <div
            id={'techtyl-toolbar-menu'}
            className={'techtyl-toolbar-menu'}
            role={'menu'}
            aria-label={t('folder.menu', { label: serverLabel })}
            ref={menu}
            onKeyDown={onKeyDown}
        >
            <button type={'button'} role={'menuitem'} disabled={busy || currentId === null} onClick={() => choose(null)}>
                <span>{t('folder.none')}</span>
                {currentId === null && <span className={'techtyl-toolbar-sr'}>{t('folder.current')}</span>}
            </button>
            {list.folders.map((folder) => (
                <button
                    type={'button'}
                    role={'menuitem'}
                    key={folder.id}
                    disabled={busy || folder.id === currentId}
                    onClick={() => choose(folder.id)}
                >
                    <span>{folder.name}</span>
                    {folder.id === currentId && <i aria-hidden={'true'} />}
                    {folder.id === currentId && <span className={'techtyl-toolbar-sr'}>{t('folder.current')}</span>}
                </button>
            ))}
            {list.folders.length === 0 && <p>{t('folder.menuEmpty')}</p>}
            {busy && <p role={'status'}>{t('folder.moving')}</p>}
            {problem && (
                <p className={'techtyl-toolbar-notice is-bad'} role={'alert'}>
                    {problem}
                </p>
            )}
        </div>,
        document.body
    );
};
