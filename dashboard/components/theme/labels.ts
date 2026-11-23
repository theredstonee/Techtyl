import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faArchive,
    faClock,
    faCodeBranch,
    faCreditCard,
    faDatabase,
    faFileAlt,
    faFolderOpen,
    faGlobeEurope,
    faHistory,
    faKey,
    faNetworkWired,
    faPaintBrush,
    faPlay,
    faPuzzlePiece,
    faRobot,
    faSlidersH,
    faTerminal,
    faThLarge,
    faTicketAlt,
    faUserCircle,
    faUserFriends,
    faUsers,
    faWrench,
    faLock,
} from '@fortawesome/free-solid-svg-icons';

export interface PageInfo {
    icon: IconDefinition;
    group: 'overview' | 'manage' | 'configure' | 'tools';
}

// Pages of a server: icon and group. Unknown pages (other extensions) end up under "tools"
// with their own name. Labels live in i18n/theme.ts.
export const serverPages: Record<string, PageInfo> = {
    '/': { icon: faTerminal, group: 'overview' },
    '/activity': { icon: faHistory, group: 'overview' },
    '/files': { icon: faFolderOpen, group: 'manage' },
    '/databases': { icon: faDatabase, group: 'manage' },
    '/backups': { icon: faArchive, group: 'manage' },
    '/schedules': { icon: faClock, group: 'manage' },
    '/users': { icon: faUsers, group: 'manage' },
    '/network': { icon: faNetworkWired, group: 'configure' },
    '/startup': { icon: faPlay, group: 'configure' },
    '/settings': { icon: faSlidersH, group: 'configure' },
    '/mods': { icon: faPuzzlePiece, group: 'tools' },
    '/versions': { icon: faCodeBranch, group: 'tools' },
    '/players': { icon: faUserFriends, group: 'tools' },
    '/worlds': { icon: faGlobeEurope, group: 'tools' },
    '/properties': { icon: faWrench, group: 'tools' },
    '/logs': { icon: faFileAlt, group: 'tools' },
};

export const serverGroups: { key: PageInfo['group'] }[] = [
    { key: 'overview' },
    { key: 'manage' },
    { key: 'configure' },
    { key: 'tools' },
];

export const accountPages: Record<string, { icon: IconDefinition }> = {
    '/': { icon: faUserCircle },
    '/api': { icon: faKey },
    '/ssh': { icon: faLock },
    '/activity': { icon: faHistory },
    '/design': { icon: faPaintBrush },
    '/tickets': { icon: faTicketAlt },
    '/billing': { icon: faCreditCard },
    '/assistant': { icon: faRobot },
};

export const fallbackIcon = faThLarge;

type Translate = (key: string, replace?: Record<string, string | number>) => string;

// Label of a page: translated if Techtyl knows the path, otherwise the name the route brings along.
export const pageLabel = (t: Translate, prefix: 's' | 'a', route: { path: string; name: string | undefined }): string => {
    const key = prefix + ':' + route.path;
    const text = t(key);
    return text === key ? route.name || route.path : text;
};

export interface NamedRoute {
    path: string;
    name: string | undefined;
    exact?: boolean;
    adminOnly?: boolean;
    identifier?: string;
    permission?: string | string[] | null;
}

export const joinPath = (base: string, path: string): string =>
    path === '/' ? base : base.replace(/\/*$/, '') + '/' + path.replace(/^\/+/, '');
