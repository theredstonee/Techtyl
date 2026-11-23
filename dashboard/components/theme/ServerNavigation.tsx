import React, { useEffect, useState } from 'react';
import { NavLink, useLocation, useRouteMatch } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronDown, faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';
import blueprintRoutes from '@blueprint/extends/routers/routes';
import BeforeSubNavigation from '@blueprint/components/Navigation/SubNavigation/BeforeSubNavigation';
import AdditionalServerItems from '@blueprint/components/Navigation/SubNavigation/AdditionalServerItems';
import AfterSubNavigation from '@blueprint/components/Navigation/SubNavigation/AfterSubNavigation';
import { bannerValue, loadBootstrap } from './bootstrap';
import { fallbackIcon, joinPath, NamedRoute, PageInfo, pageLabel, serverGroups, serverPages } from './labels';
import texts from '@blueprint/extensions/techtyl/i18n/theme';
import { useT } from '@blueprint/extensions/techtyl/i18n';

interface Entry {
    route: NamedRoute;
    info: PageInfo;
    label: string;
}

const extensionIds = Array.from(new Set((blueprintRoutes.server as NamedRoute[]).map((route) => route.identifier || '')));

// Welche Eggs die Seiten einer Erweiterung sehen duerfen ('-1' = alle), wie im Router von Blueprint.
const useExtensionEggs = () => {
    const [eggs, setEggs] = useState<Record<string, string[]>>(() =>
        extensionIds.reduce((all, id) => ({ ...all, [id]: ['-1'] }), {})
    );

    useEffect(() => {
        let active = true;
        Promise.all(
            extensionIds.map((id) =>
                fetch('/api/client/extensions/blueprint/eggs?' + new URLSearchParams({ id }).toString())
                    .then((response) => response.json())
                    .then((list) => [id, Array.isArray(list) ? list.map(String) : ['-1']] as [string, string[]])
                    .catch(() => [id, ['-1']] as [string, string[]])
            )
        ).then((pairs) => {
            if (!active) return;
            setEggs(pairs.reduce((all, pair) => ({ ...all, [pair[0]]: pair[1] }), {}));
        });
        return () => {
            active = false;
        };
    }, []);

    return eggs;
};

// Ersetzt die Reiterleiste eines Servers: Seiten in Gruppen, dazu Name und Zustand des Servers.
// Das Stylesheet setzt den Block in die Seitenleiste bzw. als eigene Spalte neben den Inhalt.
export default ({ className }: { id?: string; className?: string; children?: React.ReactNode }) => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const serverUuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.BlueprintFramework.eggId);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const eggs = useExtensionEggs();
    const [open, setOpen] = useState(false);
    const t = useT(texts);

    useEffect(() => setOpen(false), [location.pathname]);

    // Banner of this server for the page header (the stylesheet reads --techtyl-banner-current)
    useEffect(() => {
        let active = true;
        const root = document.documentElement;
        loadBootstrap().then((data) => {
            const entry = serverUuid ? data.banners[serverUuid] : undefined;
            const value = entry ? bannerValue(entry.url) : undefined;
            if (!active) return;
            if (value) root.style.setProperty('--techtyl-banner-current', value);
            else root.style.removeProperty('--techtyl-banner-current');
        });
        return () => {
            active = false;
            root.style.removeProperty('--techtyl-banner-current');
        };
    }, [serverUuid]);

    const entries: Entry[] = [
        ...(routes.server as NamedRoute[]),
        ...(blueprintRoutes.server as NamedRoute[])
            .filter((route) => !route.adminOnly || rootAdmin)
            .filter((route) => {
                const allowed = eggs[route.identifier || ''] || ['-1'];
                return allowed.indexOf('-1') >= 0 || (eggId !== undefined && allowed.indexOf(String(eggId)) >= 0);
            }),
    ]
        .filter((route) => !!route.name)
        .map((route) => ({
            route,
            info: serverPages[route.path] || { icon: fallbackIcon, group: 'tools' as const },
            label: pageLabel(t, 's', route),
        }));

    const rest = location.pathname.slice(match.url.length).replace(/\/+$/, '') || '/';
    const current = entries.filter((entry) => (entry.route.path === '/' ? rest === '/' : rest.indexOf(entry.route.path) === 0)).pop();
    const state = status || 'offline';

    const link = (entry: Entry) => (
        <NavLink
            to={joinPath(match.url, entry.route.path)}
            exact={entry.route.path === '/' || entry.route.exact}
            className={'techtyl-subnav__link'}
            title={entry.label}
        >
            <FontAwesomeIcon icon={entry.info.icon} fixedWidth />
            <span>{entry.label}</span>
        </NavLink>
    );

    return (
        <div
            id={'SubNavigation'}
            className={'techtyl-subnav techtyl-subnav--server ' + (className || '')}
            data-open={open ? 'true' : 'false'}
            data-state={state}
        >
            <BeforeSubNavigation />
            <button
                type={'button'}
                className={'techtyl-subnav__head'}
                aria-expanded={open}
                aria-controls={'techtyl-subnav-body'}
                onClick={() => setOpen((value) => !value)}
            >
                <i className={'techtyl-dot'} data-state={state} aria-hidden />
                <span className={'techtyl-subnav__title'}>
                    <b>{serverName}</b>
                    <small>
                        <span className={'techtyl-subnav__state'}>{t('state.' + state)}</span>
                        <span className={'techtyl-subnav__current'}>{current ? current.label : ''}</span>
                    </small>
                </span>
                <FontAwesomeIcon icon={faChevronDown} className={'techtyl-subnav__chevron'} />
            </button>
            <nav id={'techtyl-subnav-body'} className={'techtyl-subnav__body'} aria-label={t('nav.serverPages')} data-techtyl-track={''}>
                {serverGroups.map((group) => (
                    <div key={group.key} className={'techtyl-subnav__group'}>
                        <p className={'techtyl-nav__heading'}>{t('group.' + group.key)}</p>
                        {entries
                            .filter((entry) => entry.info.group === group.key)
                            .map((entry) =>
                                entry.route.permission ? (
                                    <Can key={entry.route.path} action={entry.route.permission} matchAny>
                                        {link(entry)}
                                    </Can>
                                ) : (
                                    <React.Fragment key={entry.route.path}>{link(entry)}</React.Fragment>
                                )
                            )}
                        {group.key === 'tools' && <AdditionalServerItems />}
                    </div>
                ))}
                {rootAdmin && (
                    <a
                        href={'/admin/servers/view/' + serverId}
                        target={'_blank'}
                        rel={'noreferrer'}
                        className={'techtyl-subnav__link techtyl-subnav__admin'}
                        title={t('nav.openAdmin')}
                    >
                        <FontAwesomeIcon icon={faExternalLinkAlt} fixedWidth />
                        <span>{t('nav.openAdmin')}</span>
                    </a>
                )}
            </nav>
            <AfterSubNavigation />
        </div>
    );
};
