import React, { useState } from 'react';
import {
    DesktopComputerIcon,
    DeviceMobileIcon,
    ExclamationIcon,
    KeyIcon,
    LoginIcon,
    ShieldCheckIcon,
    CodeIcon,
    UserIcon,
    DotsHorizontalIcon,
} from '@heroicons/react/outline';
import { Locale } from '@blueprint/extensions/techtyl/i18n';
import { ActivityEntry, Translate } from './api';
import { clock, dayKey, dayLabel, describeEvent, deviceLabel, EventCategory, parseUserAgent, relative } from './format';

const ICONS: Record<EventCategory, React.ReactNode> = {
    login: <LoginIcon />,
    fail: <ExclamationIcon />,
    security: <ShieldCheckIcon />,
    key: <KeyIcon />,
    account: <UserIcon />,
    other: <DotsHorizontalIcon />,
};

/** Properties worth showing in the details (everything as plain text). */
const detailRows = (entry: ActivityEntry): [string, string][] =>
    Object.keys(entry.properties)
        .filter((key) => key !== 'useragent' && key !== 'ip')
        .map((key) => {
            const value = entry.properties[key];
            const shown = typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : JSON.stringify(value);

            return [key, shown.length > 300 ? `${shown.slice(0, 300)}…` : shown] as [string, string];
        });

interface RowProps {
    entry: ActivityEntry;
    t: Translate;
    locale: Locale;
    compact?: boolean;
}

export const EventRow = ({ entry, t, locale, compact }: RowProps) => {
    const [open, setOpen] = useState(false);
    const { text, category } = describeEvent(t, entry.event, entry.properties);
    const ua = typeof entry.properties.useragent === 'string' ? (entry.properties.useragent as string) : null;
    const device = parseUserAgent(ua);
    const details = compact ? [] : detailRows(entry);
    const expandable = !compact && (details.length > 0 || !!ua);

    return (
        <li className={`techtyl-account-event is-${category}`}>
            <span className={'techtyl-account-event-icon'} aria-hidden>
                {ICONS[category]}
            </span>
            <div className={'techtyl-account-event-body'}>
                <p className={'techtyl-account-event-text'}>{text}</p>
                <p className={'techtyl-account-event-meta'}>
                    <time dateTime={entry.timestamp.toISOString()} title={entry.timestamp.toLocaleString(locale === 'de' ? 'de-DE' : 'en-US')}>
                        {compact ? relative(t, locale, entry.timestamp) : clock(entry.timestamp)}
                    </time>
                    {entry.ip && (
                        <>
                            <span className={'techtyl-account-dot'} aria-hidden />
                            <span className={'is-mono'}>{entry.ip}</span>
                        </>
                    )}
                    {device && (
                        <>
                            <span className={'techtyl-account-dot'} aria-hidden />
                            <span className={'techtyl-account-event-device'}>
                                {device.kind === 'mobile' ? <DeviceMobileIcon aria-hidden /> : device.kind === 'tool' ? <CodeIcon aria-hidden /> : <DesktopComputerIcon aria-hidden />}
                                {deviceLabel(t, device)}
                            </span>
                        </>
                    )}
                    {entry.isApi && <span className={'techtyl-account-badge'}>{t('via_api')}</span>}
                </p>
                {expandable && (
                    <>
                        <button type={'button'} className={'techtyl-account-event-toggle'} aria-expanded={open} onClick={() => setOpen((value) => !value)}>
                            {open ? t('details_hide') : t('details_show')}
                        </button>
                        {open && (
                            <dl className={'techtyl-account-event-details'}>
                                {details.map(([key, value]) => (
                                    <div key={key}>
                                        <dt>{key}</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                                {ua && (
                                    <div>
                                        <dt>{t('user_agent')}</dt>
                                        <dd>{ua}</dd>
                                    </div>
                                )}
                            </dl>
                        )}
                    </>
                )}
            </div>
        </li>
    );
};

/** Entries grouped by day ("Today", "Yesterday", ...). */
export const GroupedEvents = ({ items, t, locale }: { items: ActivityEntry[]; t: Translate; locale: Locale }) => {
    const groups: { key: string; label: string; items: ActivityEntry[] }[] = [];
    items.forEach((entry) => {
        const key = dayKey(entry.timestamp);
        let group = groups[groups.length - 1];
        if (!group || group.key !== key) {
            group = { key, label: dayLabel(t, locale, entry.timestamp), items: [] };
            groups.push(group);
        }
        group.items.push(entry);
    });

    return (
        <div className={'techtyl-account-days'}>
            {groups.map((group, index) => (
                <section key={group.key} className={'techtyl-account-day techtyl-account-rise'} style={{ ['--i' as any]: Math.min(index, 6) }}>
                    <h3>
                        <span>{group.label}</span>
                        <small>{t('entries', { count: group.items.length })}</small>
                    </h3>
                    <ol className={'techtyl-account-events'}>
                        {group.items.map((entry) => (
                            <EventRow key={entry.id} entry={entry} t={t} locale={locale} />
                        ))}
                    </ol>
                </section>
            ))}
        </div>
    );
};
