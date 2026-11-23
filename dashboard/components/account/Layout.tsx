import React from 'react';
import { Link } from 'react-router-dom';
import { CameraIcon, ShieldCheckIcon, ShieldExclamationIcon, StarIcon } from '@heroicons/react/outline';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import Avatar from './Avatar';
import AccountNav from './AccountNav';
import { useAccount } from './store';
import { memberFor, monthYear } from './format';
import { Button, Notice } from './ui';
import { explain } from './api';
import './styles.css';

interface Props {
    /** Document title. */
    title: string;
    /** Large profile head (overview) instead of the compact one. */
    hero?: boolean;
    /** Right side of the large head, e.g. the protection ring. */
    aside?: React.ReactNode;
    /** Short line under the name in the compact head. */
    subtitle?: string;
    children: React.ReactNode;
}

/** Frame of every account page: profile head, tabs, content. */
export default function AccountLayout({ title, hero, aside, subtitle, children }: Props) {
    const { account, error, reload } = useAccount();
    const t = useT(texts);
    const locale = useLocale();
    const fullName = account ? `${account.firstName} ${account.lastName}`.trim() : '';

    return (
        <PageContentBlock title={title}>
            <div className={`techtyl-account${hero ? ' is-hero' : ''}`}>
                <header className={`techtyl-account-head${hero ? ' techtyl-account-head--hero' : ''}`}>
                    {hero && <div className={'techtyl-account-head-band'} aria-hidden />}
                    <div className={'techtyl-account-head-main'}>
                        <Link
                            to={'/account/profile#avatar'}
                            className={'techtyl-account-head-avatar'}
                            aria-label={t('head_change_avatar')}
                            title={t('head_change_avatar')}
                        >
                            {account ? (
                                <Avatar self name={fullName || account.username} size={hero ? 96 : 48} ring />
                            ) : (
                                <span className={'techtyl-account-avatar techtyl-account-avatar--ghost'} style={{ width: hero ? 96 : 48, height: hero ? 96 : 48 }} />
                            )}
                            <span className={'techtyl-account-head-camera'} aria-hidden>
                                <CameraIcon />
                            </span>
                        </Link>
                        <div className={'techtyl-account-head-text'}>
                            {hero && <p className={'techtyl-account-eyebrow'}>{t('my_account')}</p>}
                            <h1>{account ? fullName || account.username : <span className={'techtyl-account-ghost-line'} />}</h1>
                            {account && (
                                <p className={'techtyl-account-head-sub'}>
                                    {hero ? (
                                        <>
                                            <span className={'is-mono'}>@{account.username}</span>
                                            <span className={'techtyl-account-dot'} aria-hidden />
                                            <span>{account.email}</span>
                                        </>
                                    ) : (
                                        <span>{subtitle || `@${account.username}`}</span>
                                    )}
                                </p>
                            )}
                            {hero && account && (
                                <ul className={'techtyl-account-chips'}>
                                    {account.createdAt && (
                                        <li title={memberFor(t, account.createdAt)}>
                                            {t('member_since', { date: monthYear(account.createdAt, locale) })}
                                        </li>
                                    )}
                                    <li className={account.useTotp ? 'is-ok' : 'is-warn'}>
                                        {account.useTotp ? <ShieldCheckIcon aria-hidden /> : <ShieldExclamationIcon aria-hidden />}
                                        {account.useTotp ? t('chip_totp_on') : t('chip_totp_off')}
                                    </li>
                                    {account.rootAdmin && (
                                        <li className={'is-accent'}>
                                            <StarIcon aria-hidden />
                                            {t('chip_admin')}
                                        </li>
                                    )}
                                </ul>
                            )}
                        </div>
                        {hero && aside && <div className={'techtyl-account-head-aside'}>{aside}</div>}
                    </div>
                </header>
                <AccountNav />
                {error && !account ? (
                    <Notice
                        tone={'bad'}
                        title={t('load_failed')}
                        action={
                            <Button small onClick={() => reload().catch(() => undefined)}>
                                {t('retry')}
                            </Button>
                        }
                    >
                        {explain(t, error)}
                    </Notice>
                ) : (
                    <div className={'techtyl-account-content'}>{children}</div>
                )}
            </div>
        </PageContentBlock>
    );
}
