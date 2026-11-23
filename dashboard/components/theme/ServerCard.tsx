import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faFolderOpen } from '@fortawesome/free-solid-svg-icons';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip } from '@/lib/formatters';
import BeforeEntryName from '@blueprint/components/Dashboard/Serverlist/ServerRow/BeforeEntryName';
import AfterEntryName from '@blueprint/components/Dashboard/Serverlist/ServerRow/AfterEntryName';
import BeforeEntryDescription from '@blueprint/components/Dashboard/Serverlist/ServerRow/BeforeEntryDescription';
import AfterEntryDescription from '@blueprint/components/Dashboard/Serverlist/ServerRow/AfterEntryDescription';
import ResourceLimits from '@blueprint/components/Dashboard/Serverlist/ServerRow/ResourceLimits';
import { bannerValue, loadBootstrap } from './bootstrap';
import texts from '@blueprint/extensions/techtyl/i18n/theme';
import listTexts from '@blueprint/extensions/techtyl/i18n/serverlist';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import { gameText, searchBlob } from '../serverlist/dom';
import FolderMenu from '../serverlist/FolderMenu';
import { hasId, toggleSelected, useListState } from '../serverlist/store';
import '../serverlist/styles.css';

type Timer = ReturnType<typeof setInterval>;

const share = (used: number, limit: number): number | null => (limit > 0 ? Math.max(0, Math.min(100, (used / limit) * 100)) : null);
const percent = (value: number): string => (value >= 10 || value === 0 ? String(Math.round(value)) : value.toFixed(1)) + ' %';

const Meter = ({ label, value, fallback, detail }: { label: string; value: number | null; fallback: string; detail: string }) => (
    <div className={'techtyl-meter'} data-load={value !== null && value >= 90 ? 'hoch' : 'normal'} title={detail}>
        <span className={'techtyl-meter__label'}>{label}</span>
        <b className={'techtyl-meter__value'}>{value === null ? fallback : percent(value)}</b>
        <i className={'techtyl-meter__track'} aria-hidden>
            <u style={{ transform: 'scaleX(' + ((value || 0) / 100).toFixed(3) + ')' }} />
        </i>
    </div>
);

// Ersetzt die Zeile der Serverliste: Karte mit Banner, Zustand, Adresse und Auslastung in Prozent.
// Anordnung (Raster, Liste, Kompakt) und Banner an/aus regelt das Stylesheet ueber data-techtyl-*.
// Der Link liegt ueber der Karte, die Knopfe darueber, damit die Karte kein Knopf im Link wird.
export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const menuButton = useRef<HTMLButtonElement>(null);
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);
    const [banner, setBanner] = useState<string | undefined>(undefined);
    const [menu, setMenu] = useState(false);
    const t = useT(texts);
    const tList = useT(listTexts);
    const list = useListState();

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        // Gesperrte Server liefern keine Auslastung
        if (isSuspended) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended]);

    useEffect(() => {
        let active = true;
        loadBootstrap().then((data) => {
            const entry = data.banners[server.uuid];
            if (active && entry) setBanner(bannerValue(entry.url));
        });
        return () => {
            active = false;
        };
    }, [server.uuid]);

    useEffect(() => {
        const onOpen = (event: Event) => {
            const custom = event as CustomEvent;
            if (custom.detail !== server.uuid) setMenu(false);
        };
        window.addEventListener('techtyl-folder-menu', onOpen);
        return () => window.removeEventListener('techtyl-folder-menu', onOpen);
    }, [server.uuid]);

    const allocation = server.allocations.find((item) => item.isDefault);
    const address = allocation ? (allocation.alias || ip(allocation.ip)) + ':' + allocation.port : '';

    let state = 'pending';
    let stateText = t('state.pending');
    if (isSuspended) {
        state = 'blocked';
        stateText = t(server.status === 'suspended' ? 'state.suspended' : 'state.error');
    } else if (server.isTransferring) {
        state = 'busy';
        stateText = t('state.transferring');
    } else if (server.status === 'installing') {
        state = 'busy';
        stateText = t('state.installing');
    } else if (server.status === 'restoring_backup') {
        state = 'busy';
        stateText = t('state.restoring');
    } else if (server.status) {
        state = 'blocked';
        stateText = t('state.unavailable');
    } else if (stats) {
        state = stats.status;
        stateText = t('state.' + stats.status);
    }

    const memoryLimit = server.limits.memory * 1024 * 1024;
    const diskLimit = server.limits.disk * 1024 * 1024;
    const detail = (used: string, limit: string | null) => t('meter.of', { used, limit: limit === null ? t('meter.unlimited') : limit });
    const style = (banner ? { '--techtyl-banner': banner } : {}) as React.CSSProperties;
    const fact = list.facts[server.uuid.toLowerCase()];
    const parts: Array<string | null | undefined> = [server.name, server.description];
    for (let i = 0; i < server.allocations.length; i++) {
        const item = server.allocations[i];
        parts.push(item.alias);
        parts.push(item.ip);
        parts.push(String(item.port));
    }
    parts.push(address);
    parts.push(gameText(fact ? fact.egg : '', server.eggFeatures || [], server.dockerImage || ''));

    const closeMenu = () => {
        setMenu(false);
        const node = menuButton.current;
        if (node) node.focus();
    };

    const toggleMenu = () => {
        if (menu) {
            setMenu(false);
            return;
        }
        window.dispatchEvent(new CustomEvent('techtyl-folder-menu', { detail: server.uuid }));
        setMenu(true);
    };

    return (
        <div
            className={'techtyl-card ' + (className || '')}
            data-state={state}
            data-banner={banner ? 'eigen' : 'ohne'}
            data-techtyl-server-id={server.uuid}
            data-techtyl-search={searchBlob(parts)}
            data-techtyl-owner={fact ? (fact.owner ? 'mine' : 'shared') : undefined}
            data-techtyl-selecting={list.selecting ? '1' : undefined}
            style={style}
        >
            <div className={'techtyl-toolbar-face'}>
                <span className={'techtyl-card__banner'} aria-hidden />
                <div className={'techtyl-card__body'}>
                    <div className={'techtyl-card__head'}>
                        <BeforeEntryName />
                        <h3 className={'techtyl-card__name'}>{server.name}</h3>
                        <AfterEntryName />
                        <span className={'techtyl-card__state'}>
                            <i className={'techtyl-dot'} data-state={state} aria-hidden />
                            {stateText}
                        </span>
                    </div>
                    <div className={'techtyl-card__meta'}>
                        <BeforeEntryDescription />
                        {address && <span className={'techtyl-card__address'}>{address}</span>}
                        {!!server.description && <span className={'techtyl-card__text'}>{server.description}</span>}
                        <AfterEntryDescription />
                    </div>
                    {stats && !isSuspended ? (
                        <div className={'techtyl-card__meters'}>
                            <Meter
                                label={t('meter.cpu')}
                                value={share(stats.cpuUsagePercent, server.limits.cpu)}
                                fallback={stats.cpuUsagePercent.toFixed(0) + ' %'}
                                detail={detail(stats.cpuUsagePercent.toFixed(2) + ' %', server.limits.cpu > 0 ? server.limits.cpu + ' %' : null)}
                            />
                            <Meter
                                label={t('meter.ram')}
                                value={share(stats.memoryUsageInBytes, memoryLimit)}
                                fallback={bytesToString(stats.memoryUsageInBytes)}
                                detail={detail(bytesToString(stats.memoryUsageInBytes), memoryLimit > 0 ? bytesToString(memoryLimit) : null)}
                            />
                            <Meter
                                label={t('meter.disk')}
                                value={share(stats.diskUsageInBytes, diskLimit)}
                                fallback={bytesToString(stats.diskUsageInBytes)}
                                detail={detail(bytesToString(stats.diskUsageInBytes), diskLimit > 0 ? bytesToString(diskLimit) : null)}
                            />
                            <ResourceLimits />
                        </div>
                    ) : (
                        <div className={'techtyl-card__meters'} data-empty={state === 'pending' ? 'laedt' : 'ohne'} aria-hidden>
                            <i />
                            <i />
                            <i />
                        </div>
                    )}
                    <svg className={'techtyl-card__pulse'} viewBox={'0 0 300 16'} preserveAspectRatio={'none'} aria-hidden>
                        <path
                            d={
                                state === 'running' || state === 'starting'
                                    ? 'M0 8h70l6-6 8 12 6-9 5 3h80l6-6 8 12 6-9 5 3h94'
                                    : 'M0 8h300'
                            }
                            pathLength={300}
                        />
                    </svg>
                </div>
            </div>
            <Link className={'techtyl-toolbar-hit'} to={'/server/' + server.id} aria-label={server.name + ', ' + stateText} />
            <div className={'techtyl-toolbar-tools'}>
                {list.selecting && (
                    <label className={'techtyl-toolbar-check'}>
                        <input
                            type={'checkbox'}
                            checked={hasId(list.selected, server.uuid)}
                            aria-label={tList('select.one', { label: server.name })}
                            onChange={() => toggleSelected(server.uuid)}
                        />
                    </label>
                )}
                <button
                    type={'button'}
                    className={'techtyl-toolbar-fold'}
                    ref={menuButton}
                    aria-haspopup={'menu'}
                    aria-expanded={menu}
                    aria-label={tList('folder.move')}
                    onClick={toggleMenu}
                >
                    <FontAwesomeIcon icon={faFolderOpen} />
                </button>
            </div>
            {menu && (
                <FolderMenu anchor={menuButton} serverId={server.uuid} serverLabel={server.name} onClose={closeMenu} />
            )}
        </div>
    );
};
