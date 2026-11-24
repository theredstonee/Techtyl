import React from 'react';
import { NavLink } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { ApplicationStore } from '@/state';
import routes from '@/routers/routes';
import blueprintRoutes from '@blueprint/extends/routers/routes';
import BeforeSubNavigation from '@blueprint/components/Navigation/SubNavigation/BeforeSubNavigation';
import AdditionalAccountItems from '@blueprint/components/Navigation/SubNavigation/AdditionalAccountItems';
import AfterSubNavigation from '@blueprint/components/Navigation/SubNavigation/AfterSubNavigation';
import { useShellBootstrap } from './bootstrap';
import { joinPath, NamedRoute } from './labels';
import { decorateAccount, isForeign, isHidden } from './integrate';
import texts from '@blueprint/extensions/techtyl/i18n/theme';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';

// Account pages also live in the main navigation. This column is what the
// router mounts on /account. Insertion points are rendered here. The same
// nodes arrive as children from the router, so those children are not
// rendered again.
export default ({ className }: { id?: string; className?: string; children?: React.ReactNode }) => {
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const t = useT(texts);
    const locale = useLocale();
    const shell = useShellBootstrap();

    const accountRoutes: NamedRoute[] = [
        ...(routes.account as NamedRoute[]),
        ...(blueprintRoutes.account as NamedRoute[]).filter((route) => !route.adminOnly || rootAdmin),
    ].filter((route) => !!route.name && !isHidden('account', route, shell.navHidden));

    const ownAccount = accountRoutes.filter((route) => !isForeign(route));
    const foreignAccount = accountRoutes
        .map((route, index) => ({ route, index, view: decorateAccount(route, shell.integrations, locale, t) }))
        .filter((item) => isForeign(item.route))
        .sort((a, b) => a.view.order - b.view.order || a.index - b.index);

    const link = (route: NamedRoute) => {
        const view = decorateAccount(route, shell.integrations, locale, t);
        return (
            <NavLink
                key={(route.identifier || 'core') + route.path}
                to={joinPath('/account', route.path)}
                exact={route.path === '/' || route.exact}
                className={'techtyl-subnav__link'}
                title={view.label}
            >
                <FontAwesomeIcon icon={view.icon} fixedWidth />
                <span>{view.label}</span>
            </NavLink>
        );
    };

    return (
        <div id={'SubNavigation'} className={'techtyl-subnav techtyl-subnav--account ' + (className || '')}>
            <BeforeSubNavigation />
            <div className={'techtyl-subnav__group'}>
                <p className={'techtyl-nav__heading'}>{t('nav.account')}</p>
                {ownAccount.map((route) => link(route))}
            </div>
            {foreignAccount.length > 0 && (
                <div className={'techtyl-subnav__group'} data-techtyl-group={'extensions'}>
                    <p className={'techtyl-nav__heading'}>{t('group.extensions')}</p>
                    {foreignAccount.map((item) => link(item.route))}
                </div>
            )}
            <AdditionalAccountItems />
            <AfterSubNavigation />
        </div>
    );
};
