import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
    ChatAlt2Icon,
    ClockIcon,
    ColorSwatchIcon,
    CreditCardIcon,
    HomeIcon,
    KeyIcon,
    ShieldCheckIcon,
    SparklesIcon,
    TerminalIcon,
    UserIcon,
} from '@heroicons/react/outline';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import { useAccount } from './store';

/*
 * Reiter des Kontobereichs. Eigene Seiten (Uebersicht bis Aktivitaet) und die Techtyl-Seiten der
 * anderen Module (Design, Tickets, Abrechnung, KI-Assistent; nur wenn das Modul an ist).
 * Andere Seiten unter /account koennen diese Leiste ebenfalls einbinden:
 *   import AccountNav from '../../account/AccountNav';
 */

interface Item {
    to: string;
    label: string;
    icon: React.ReactNode;
    exact?: boolean;
    show?: boolean;
}

export default function AccountNav() {
    const { account } = useAccount();
    const t = useT(texts);
    const location = useLocation();
    const list = useRef<HTMLDivElement>(null);
    const [bar, setBar] = useState<{ left: number; width: number } | null>(null);

    const own: Item[] = [
        { to: '/account', label: t('tab_overview'), icon: <HomeIcon />, exact: true },
        { to: '/account/profile', label: t('tab_profile'), icon: <UserIcon /> },
        { to: '/account/security', label: t('tab_security'), icon: <ShieldCheckIcon /> },
        { to: '/account/api', label: t('tab_api'), icon: <KeyIcon /> },
        { to: '/account/ssh', label: t('tab_ssh'), icon: <TerminalIcon /> },
        { to: '/account/activity', label: t('tab_activity'), icon: <ClockIcon /> },
    ];
    const more: Item[] = [
        { to: '/account/design', label: t('tab_design'), icon: <ColorSwatchIcon /> },
        { to: '/account/tickets', label: t('tab_tickets'), icon: <ChatAlt2Icon />, show: account?.modules.tickets },
        { to: '/account/billing', label: t('tab_billing'), icon: <CreditCardIcon />, show: account?.modules.billing },
        { to: '/account/assistant', label: t('tab_assistant'), icon: <SparklesIcon />, show: account?.modules.assistant },
    ].filter((item) => item.show !== false && (item.show === undefined || item.show));

    // Leuchtende Leiste unter dem aktiven Reiter: misst den aktiven Link und gleitet dorthin.
    const measure = () => {
        const active = list.current?.querySelector<HTMLElement>('a.is-active');
        if (!active || !list.current) {
            setBar(null);

            return;
        }
        setBar({ left: active.offsetLeft, width: active.offsetWidth });
    };

    useLayoutEffect(measure, [location.pathname, more.length, t('tab_overview')]);

    useEffect(() => {
        const active = list.current?.querySelector<HTMLElement>('a.is-active');
        if (active && list.current && list.current.scrollWidth > list.current.clientWidth) {
            const target = active.offsetLeft - (list.current.clientWidth - active.offsetWidth) / 2;
            list.current.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
        }
        window.addEventListener('resize', measure);
        const fonts = (document as any).fonts;
        fonts?.ready?.then?.(measure);

        return () => window.removeEventListener('resize', measure);
    }, [location.pathname]);

    const link = (item: Item) => (
        <NavLink key={item.to} to={item.to} exact={item.exact} activeClassName={'is-active'} className={'techtyl-account-tab'}>
            <span className={'techtyl-account-tab-icon'} aria-hidden>
                {item.icon}
            </span>
            <span>{item.label}</span>
        </NavLink>
    );

    return (
        <nav className={'techtyl-account-nav'} aria-label={t('nav_label')}>
            <div className={'techtyl-account-tabs'} ref={list}>
                {own.map(link)}
                {more.length > 0 && <span className={'techtyl-account-tabs-gap'} aria-hidden />}
                {more.map(link)}
                {bar && <i className={'techtyl-account-tabs-bar'} style={{ transform: `translateX(${bar.left}px)`, width: bar.width }} aria-hidden />}
            </div>
        </nav>
    );
}
