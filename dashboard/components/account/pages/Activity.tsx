import React, { useEffect, useRef, useState } from 'react';
import { useHistory, useLocation } from 'react-router-dom';
import { ClockIcon } from '@heroicons/react/outline';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import AccountLayout from '../Layout';
import { ActivityEntry, explain, getActivity } from '../api';
import { Button, Card, Empty, Notice, Skeleton } from '../ui';
import { GroupedEvents } from '../ActivityList';

/** Filters map to the panel's partial match on the event name. */
const FILTERS: { key: string; event?: string; label: string }[] = [
    { key: 'all', label: 'filter_all' },
    { key: 'login', event: 'auth:', label: 'filter_logins' },
    { key: 'fail', event: 'auth:fail', label: 'filter_failed' },
    { key: 'security', event: 'two-factor', label: 'filter_security' },
    { key: 'account', event: 'user:account', label: 'filter_account' },
    { key: 'keys', event: 'key', label: 'filter_keys' },
];

export default function AccountActivity() {
    const t = useT(texts);
    const locale = useLocale();
    const history = useHistory();
    const location = useLocation();
    const initial = new URLSearchParams(location.search).get('filter') || 'all';
    const [filter, setFilter] = useState(FILTERS.some((item) => item.key === initial) ? initial : 'all');
    const [items, setItems] = useState<ActivityEntry[] | null>(null);
    const [page, setPage] = useState(1);
    const [pages, setPages] = useState(1);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const request = useRef(0);

    const load = (nextPage: number, replace: boolean) => {
        const id = ++request.current;
        setLoading(true);
        setError(null);
        getActivity({ event: FILTERS.find((item) => item.key === filter)?.event, page: nextPage, perPage: 50 })
            .then((result) => {
                if (id !== request.current) return;
                setItems((current) => (replace || !current ? result.items : current.concat(result.items)));
                setPage(result.page);
                setPages(result.totalPages);
                setTotal(result.total);
            })
            .catch((e) => id === request.current && setError(explain(t, e)))
            .then(() => id === request.current && setLoading(false));
    };

    useEffect(() => {
        setItems(null);
        load(1, true);
        const search = filter === 'all' ? '' : `?filter=${filter}`;
        if (location.search !== search) history.replace({ pathname: location.pathname, search });
    }, [filter]);

    return (
        <AccountLayout title={t('title_activity')} subtitle={t('activity_subtitle')}>
            <Card index={0}>
                <div className={'techtyl-account-card-head'}>
                    <div>
                        <h2>{t('activity_title')}</h2>
                        <p>{items === null ? t('loading') : t('activity_count', { count: total })}</p>
                    </div>
                </div>
                <div className={'techtyl-account-filters'} role={'group'} aria-label={t('filter_label')}>
                    {FILTERS.map((item) => (
                        <button key={item.key} type={'button'} aria-pressed={filter === item.key} onClick={() => setFilter(item.key)}>
                            {t(item.label)}
                        </button>
                    ))}
                </div>
                {error && (
                    <Notice
                        tone={'bad'}
                        title={t('load_failed')}
                        action={
                            <Button small onClick={() => load(items ? page + 1 : 1, !items)}>
                                {t('retry')}
                            </Button>
                        }
                    >
                        {error}
                    </Notice>
                )}
                {items === null ? (
                    !error && <Skeleton lines={6} />
                ) : items.length === 0 ? (
                    <Empty icon={<ClockIcon />} title={t('activity_empty')}>
                        {filter === 'all' ? t('activity_empty_text') : t('activity_empty_filter')}
                    </Empty>
                ) : (
                    <>
                        <GroupedEvents items={items} t={t} locale={locale} />
                        {page < pages && (
                            <div className={'techtyl-account-more'}>
                                <Button busy={loading} onClick={() => load(page + 1, false)}>
                                    {t('load_more')}
                                </Button>
                                <small>{t('shown_of', { shown: items.length, total })}</small>
                            </div>
                        )}
                    </>
                )}
            </Card>
        </AccountLayout>
    );
}
