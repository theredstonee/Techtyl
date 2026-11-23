import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useHistory, useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faCogs,
    faLayerGroup,
    faPlay,
    faPowerOff,
    faRedo,
    faSearch,
    faServer,
    faSignOutAlt,
} from '@fortawesome/free-solid-svg-icons';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';
import routes from '@/routers/routes';
import blueprintRoutes from '@blueprint/extends/routers/routes';
import { accountPages, fallbackIcon, joinPath, NamedRoute, pageLabel, serverPages } from './labels';
import texts from '@blueprint/extensions/techtyl/i18n/theme';
import { useT } from '@blueprint/extensions/techtyl/i18n';

interface Command {
    key: string;
    section: string;
    label: string;
    hint?: string;
    icon: IconDefinition;
    run: () => void;
}

const matches = (command: Command, words: string[]) => {
    const text = (command.label + ' ' + (command.hint || '')).toLowerCase();
    return words.every((word) => text.indexOf(word) >= 0);
};

const sectionOrder = ['this', 'servers', 'pages'];

// Strg+K: Seiten, Server und Aktionen suchen und mit der Tastatur ausloesen.
export default ({ onClose, onSignOut }: { onClose: () => void; onSignOut: () => void }) => {
    const history = useHistory();
    const location = useLocation();
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const t = useT(texts);
    const [query, setQuery] = useState('');
    const [servers, setServers] = useState<Server[] | null>(null);
    const [selected, setSelected] = useState(0);
    const [notice, setNotice] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const previous = useRef<Element | null>(document.activeElement);

    const serverMatch = /^\/server\/([a-z0-9]+)/i.exec(location.pathname);
    const serverKey = serverMatch ? serverMatch[1] : '';

    useEffect(() => {
        if (inputRef.current) inputRef.current.focus();
        return () => {
            if (previous.current instanceof HTMLElement) previous.current.focus();
        };
    }, []);

    // Serversuche ueber die API des Panels, kurz verzoegert
    useEffect(() => {
        let active = true;
        const text = query.trim();
        const timer = setTimeout(() => {
            getServers({ query: text.length >= 2 ? text : undefined, page: 1 })
                .then((result) => active && setServers(result.items))
                .catch(() => active && setServers([]));
        }, 180);
        return () => {
            active = false;
            clearTimeout(timer);
        };
    }, [query]);

    const go = (path: string) => () => {
        history.push(path);
        onClose();
    };

    const power = (signal: string, done: string) => () => {
        if (!serverKey) return;
        setNotice(t('palette.sending'));
        http.post('/api/client/servers/' + serverKey + '/power', { signal })
            .then(() => {
                setNotice(done);
                setTimeout(onClose, 700);
            })
            .catch(() => setNotice(t('palette.failed')));
    };

    const commands = useMemo<Command[]>(() => {
        const list: Command[] = [];
        if (serverKey) {
            const base = '/server/' + serverKey;
            list.push(
                { key: 'p-start', section: 'this', label: t('palette.start'), hint: t('palette.action'), icon: faPlay, run: power('start', t('palette.started')) },
                { key: 'p-restart', section: 'this', label: t('palette.restart'), hint: t('palette.action'), icon: faRedo, run: power('restart', t('palette.restarted')) },
                { key: 'p-stop', section: 'this', label: t('palette.stop'), hint: t('palette.action'), icon: faPowerOff, run: power('stop', t('palette.stopped')) }
            );
            [...(routes.server as NamedRoute[]), ...(blueprintRoutes.server as NamedRoute[])]
                .filter((route) => !!route.name && (!route.adminOnly || rootAdmin))
                .forEach((route) => {
                    const info = serverPages[route.path];
                    list.push({
                        key: 's' + route.path,
                        section: 'this',
                        label: pageLabel(t, 's', route),
                        hint: t('palette.page'),
                        icon: info ? info.icon : fallbackIcon,
                        run: go(joinPath(base, route.path)),
                    });
                });
        }
        list.push({ key: 'home', section: 'pages', label: t('nav.servers'), hint: t('palette.overview'), icon: faLayerGroup, run: go('/') });
        [...(routes.account as NamedRoute[]), ...(blueprintRoutes.account as NamedRoute[])]
            .filter((route) => !!route.name && (!route.adminOnly || rootAdmin))
            .forEach((route) => {
                const info = accountPages[route.path];
                list.push({
                    key: 'a' + route.path,
                    section: 'pages',
                    label: pageLabel(t, 'a', route),
                    hint: t('nav.account'),
                    icon: info ? info.icon : fallbackIcon,
                    run: go(joinPath('/account', route.path)),
                });
            });
        if (rootAdmin) {
            list.push({
                key: 'admin',
                section: 'pages',
                label: t('palette.adminArea'),
                icon: faCogs,
                run: () => {
                    window.location.href = '/admin';
                },
            });
        }
        list.push({ key: 'out', section: 'pages', label: t('nav.signOut'), icon: faSignOutAlt, run: onSignOut });
        (servers || []).forEach((server) => {
            const allocation = server.allocations.find((item) => item.isDefault);
            list.push({
                key: 'srv' + server.uuid,
                section: 'servers',
                label: server.name,
                hint: allocation ? (allocation.alias || allocation.ip) + ':' + allocation.port : server.id,
                icon: faServer,
                run: go('/server/' + server.id),
            });
        });

        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        return list
            .filter((command) => command.section === 'servers' || matches(command, words))
            .sort((a, b) => sectionOrder.indexOf(a.section) - sectionOrder.indexOf(b.section));
    }, [query, servers, serverKey, rootAdmin]);

    useEffect(() => setSelected(0), [query, servers]);

    useEffect(() => {
        const row = listRef.current ? listRef.current.querySelector('[aria-selected="true"]') : null;
        if (row && (row as HTMLElement).scrollIntoView) (row as HTMLElement).scrollIntoView({ block: 'nearest' });
    }, [selected]);

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            onClose();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            setSelected((index) => (commands.length ? (index + 1) % commands.length : 0));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setSelected((index) => (commands.length ? (index - 1 + commands.length) % commands.length : 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (commands[selected]) commands[selected].run();
        } else if (event.key === 'Tab') {
            // Fokus bleibt im Dialog
            event.preventDefault();
        }
    };

    let lastSection = '';

    return createPortal(
        <div className={'techtyl-palette'} onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div className={'techtyl-palette__box'} role={'dialog'} aria-modal aria-label={t('palette.label')} onKeyDown={onKeyDown}>
                <div className={'techtyl-palette__field'}>
                    <FontAwesomeIcon icon={faSearch} />
                    <input
                        ref={inputRef}
                        type={'text'}
                        value={query}
                        onChange={(event) => setQuery(event.target.value.slice(0, 80))}
                        placeholder={t('palette.placeholder')}
                        aria-label={t('palette.query')}
                        aria-controls={'techtyl-palette-list'}
                        aria-activedescendant={commands[selected] ? 'techtyl-cmd-' + selected : undefined}
                        autoComplete={'off'}
                        spellCheck={false}
                    />
                    <kbd>Esc</kbd>
                </div>
                <div id={'techtyl-palette-list'} className={'techtyl-palette__list'} role={'listbox'} ref={listRef}>
                    {commands.map((command, index) => {
                        const heading = command.section !== lastSection ? command.section : '';
                        lastSection = command.section;
                        return (
                            <React.Fragment key={command.key}>
                                {heading && (
                                    <p className={'techtyl-palette__section'}>
                                        {t(heading === 'this' ? 'palette.thisServer' : 'palette.' + heading)}
                                    </p>
                                )}
                                <button
                                    type={'button'}
                                    id={'techtyl-cmd-' + index}
                                    role={'option'}
                                    aria-selected={index === selected}
                                    className={'techtyl-palette__item'}
                                    tabIndex={-1}
                                    onMouseMove={() => index !== selected && setSelected(index)}
                                    onClick={command.run}
                                >
                                    <FontAwesomeIcon icon={command.icon} fixedWidth />
                                    <span>{command.label}</span>
                                    {command.hint && <small>{command.hint}</small>}
                                </button>
                            </React.Fragment>
                        );
                    })}
                    {commands.length === 0 && (
                        <p className={'techtyl-palette__empty'}>{servers === null ? t('palette.searching') : t('palette.nothing')}</p>
                    )}
                </div>
                <div className={'techtyl-palette__foot'} aria-live={'polite'}>
                    {notice ? (
                        <span>{notice}</span>
                    ) : (
                        <span>
                            <kbd>↑</kbd>
                            <kbd>↓</kbd> {t('palette.choose')} <kbd>Enter</kbd> {t('palette.open')}
                        </span>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
};
