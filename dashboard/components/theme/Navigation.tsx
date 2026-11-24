import React, { useEffect, useRef, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBars,
    faChevronUp,
    faCogs,
    faLayerGroup,
    faSearch,
    faSignOutAlt,
    faTimes,
    faUserCircle,
} from '@fortawesome/free-solid-svg-icons';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import routes from '@/routers/routes';
import blueprintRoutes from '@blueprint/extends/routers/routes';
import BeforeNavigation from '@blueprint/components/Navigation/NavigationBar/BeforeNavigation';
import AdditionalItems from '@blueprint/components/Navigation/NavigationBar/AdditionalItems';
import AfterNavigation from '@blueprint/components/Navigation/NavigationBar/AfterNavigation';
import './integration.css';
import CommandPalette from './CommandPalette';
import { useShellBootstrap } from './bootstrap';
import { joinPath, NamedRoute } from './labels';
import { decorateAccount, isForeign, isHidden } from './integrate';
import texts from '@blueprint/extensions/techtyl/i18n/theme';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';

// Ersetzt die Kopfleiste des Panels. Je nach data-techtyl-nav ordnet das Stylesheet dieselben
// Elemente als Seitenleiste, Symbol-Leiste oder Leiste oben an; schmal wird daraus ein Menue.
export default () => {
    const siteName = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const user = useStoreState((state: ApplicationStore) => state.user.data!);
    const location = useLocation();
    const t = useT(texts);
    const locale = useLocale();
    const shell = useShellBootstrap();
    const [palette, setPalette] = useState(false);
    const [drawer, setDrawer] = useState(false);
    const [menu, setMenu] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setDrawer(false);
        setMenu(false);
    }, [location.pathname]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPalette((open) => !open);
            } else if (event.key === 'Escape') {
                setMenu(false);
                setDrawer(false);
            }
        };
        const onClick = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) setMenu(false);
        };
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);
        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, []);

    const signOut = () => {
        setLeaving(true);
        http.post('/auth/logout').finally(() => {
            window.location.href = '/';
        });
    };

    const accountRoutes: NamedRoute[] = [
        ...(routes.account as NamedRoute[]),
        ...(blueprintRoutes.account as NamedRoute[]).filter((route) => !route.adminOnly || user.rootAdmin),
    ].filter((route) => !!route.name && !isHidden('account', route, shell.navHidden));

    const ownAccount = accountRoutes.filter((route) => !isForeign(route));
    const foreignAccount = accountRoutes
        .map((route, index) => ({ route, index, view: decorateAccount(route, shell.integrations, locale, t) }))
        .filter((item) => isForeign(item.route))
        .sort((a, b) => a.view.order - b.view.order || a.index - b.index);

    const accountLink = (route: NamedRoute) => {
        const view = decorateAccount(route, shell.integrations, locale, t);
        return (
            <NavLink
                key={(route.identifier || 'core') + route.path}
                to={joinPath('/account', route.path)}
                exact={route.path === '/' || route.exact}
                className={'techtyl-nav__link'}
                title={view.label}
            >
                <FontAwesomeIcon icon={view.icon} fixedWidth />
                <span>{view.label}</span>
            </NavLink>
        );
    };

    // Initials until account/Avatar.tsx builds on its own (it still imports a missing dictionary)
    const initials = (user.username || '?').slice(0, 2);
    const inServers = location.pathname === '/' || location.pathname.indexOf('/server') === 0;

    return (
        <header id={'NavigationBar'} className={'techtyl-nav'} data-open={drawer ? 'true' : 'false'} aria-busy={leaving}>
            <BeforeNavigation />
            <div className={'techtyl-nav__bar'}>
                <div id={'logo'} className={'techtyl-nav__brand'}>
                    <Link to={'/'} title={siteName} aria-label={siteName}>
                        <span>{siteName}</span>
                        {/* Mark for the narrow icon rail: the T and the Techtyl dot. Hidden in the other layouts. */}
                        <svg className={'techtyl-nav__mark'} viewBox={'0 0 256 256'} aria-hidden={'true'} focusable={'false'}>
                            <path className={'techtyl-nav__mark-t'} d={'M56 70h98v34h-32v88H88v-88H56z'} />
                            <rect className={'techtyl-nav__mark-dot'} x={166} y={70} width={34} height={34} rx={7} />
                        </svg>
                    </Link>
                </div>
                <button
                    type={'button'}
                    className={'techtyl-nav__icon techtyl-nav__search-small'}
                    aria-label={t('nav.search')}
                    onClick={() => setPalette(true)}
                >
                    <FontAwesomeIcon icon={faSearch} />
                </button>
                <button
                    type={'button'}
                    className={'techtyl-nav__icon techtyl-nav__toggle'}
                    aria-label={drawer ? t('nav.menuClose') : t('nav.menuOpen')}
                    aria-expanded={drawer}
                    aria-controls={'techtyl-nav-panel'}
                    onClick={() => setDrawer((open) => !open)}
                >
                    <FontAwesomeIcon icon={drawer ? faTimes : faBars} />
                </button>
            </div>

            <div id={'techtyl-nav-panel'} className={'techtyl-nav__panel'}>
                <button
                    type={'button'}
                    className={'techtyl-nav__search'}
                    onClick={() => setPalette(true)}
                    title={t('nav.searchHint')}
                >
                    <FontAwesomeIcon icon={faSearch} fixedWidth />
                    <span>{t('nav.search')}</span>
                    <kbd>{t('nav.kbd')}</kbd>
                </button>

                <nav className={'techtyl-nav__links'} aria-label={t('nav.main')} data-techtyl-track={''}>
                    <NavLink
                        to={'/'}
                        exact
                        id={'NavigationDashboard'}
                        className={'techtyl-nav__link'}
                        isActive={() => inServers}
                        title={t('nav.servers')}
                    >
                        <FontAwesomeIcon icon={faLayerGroup} fixedWidth />
                        <span>{t('nav.servers')}</span>
                    </NavLink>
                    <p className={'techtyl-nav__heading'}>{t('nav.account')}</p>
                    {ownAccount.map((route) => accountLink(route))}
                    {foreignAccount.length > 0 && (
                        <div className={'techtyl-nav__extensions'}>
                            <p className={'techtyl-nav__heading'}>{t('group.extensions')}</p>
                            {foreignAccount.map((item) => accountLink(item.route))}
                        </div>
                    )}
                    <div className={'techtyl-nav__extra'}>
                        <AdditionalItems />
                    </div>
                </nav>

                <div className={'techtyl-nav__foot'}>
                    {user.rootAdmin && (
                        <a href={'/admin'} rel={'noreferrer'} id={'NavigationAdmin'} className={'techtyl-nav__link'} title={t('nav.admin')}>
                            <FontAwesomeIcon icon={faCogs} fixedWidth />
                            <span>{t('nav.admin')}</span>
                        </a>
                    )}
                    <div className={'techtyl-nav__user'} ref={menuRef}>
                        <button
                            type={'button'}
                            id={'NavigationAccount'}
                            className={'techtyl-nav__user-button'}
                            aria-haspopup={'menu'}
                            aria-expanded={menu}
                            onClick={() => setMenu((open) => !open)}
                            title={user.username}
                        >
                            <span className={'techtyl-nav__avatar'} aria-hidden>
                                {initials}
                            </span>
                            <span className={'techtyl-nav__who'}>
                                <b>{user.username}</b>
                                <small>{user.email}</small>
                            </span>
                            <FontAwesomeIcon icon={faChevronUp} className={'techtyl-nav__chevron'} />
                        </button>
                        {menu && (
                            <div className={'techtyl-menu'} role={'menu'}>
                                <Link to={'/account'} role={'menuitem'} className={'techtyl-menu__item'}>
                                    <FontAwesomeIcon icon={faUserCircle} fixedWidth />
                                    {t('nav.account')}
                                </Link>
                                <button
                                    type={'button'}
                                    role={'menuitem'}
                                    id={'NavigationLogout'}
                                    className={'techtyl-menu__item'}
                                    onClick={signOut}
                                    disabled={leaving}
                                >
                                    <FontAwesomeIcon icon={faSignOutAlt} fixedWidth />
                                    {leaving ? t('nav.signingOut') : t('nav.signOut')}
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <AfterNavigation />
            {palette && <CommandPalette onClose={() => setPalette(false)} onSignOut={signOut} />}
        </header>
    );
};
