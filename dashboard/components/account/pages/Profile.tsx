import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { GlobeAltIcon, IdentificationIcon, LockClosedIcon, MailIcon, PhotographIcon } from '@heroicons/react/outline';
import { useStoreActions } from 'easy-peasy';
import updateAccountEmail from '@/api/account/updateAccountEmail';
import { fallbackLocale, useLocale, useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import AccountLayout from '../Layout';
import AvatarEditor from '../AvatarEditor';
import { explain, updateLanguage, updateProfile } from '../api';
import { patchAccount, setAccount, useAccount } from '../store';
import { Button, Field, Notice, Saved, Section, Skeleton, useSaved } from '../ui';

/** Scroll to #avatar, #email ... when the page is opened with a hash. */
export const useHashScroll = (ready: boolean) => {
    const { hash } = useLocation();
    useEffect(() => {
        if (!ready || !hash) return;
        const target = document.getElementById(hash.slice(1));
        if (target) {
            window.setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.classList.add('is-target');
                window.setTimeout(() => target.classList.remove('is-target'), 1600);
            }, 120);
        }
    }, [ready, hash]);
};

export default function AccountProfile() {
    const t = useT(texts);
    const { account } = useAccount();
    useHashScroll(!!account);

    return (
        <AccountLayout title={t('title_profile')} subtitle={t('profile_subtitle')}>
            <Section id={'avatar'} index={0} icon={<PhotographIcon />} title={t('avatar_title')} description={t('avatar_text')}>
                {account ? <AvatarEditor /> : <Skeleton lines={3} />}
            </Section>
            <Section id={'personal'} index={1} icon={<IdentificationIcon />} title={t('personal_title')} description={t('personal_text')}>
                {account ? <NameForm /> : <Skeleton lines={3} />}
            </Section>
            <Section id={'email'} index={2} icon={<MailIcon />} title={t('email_title')} description={t('email_text')}>
                {account ? <EmailForm /> : <Skeleton lines={3} />}
            </Section>
            <Section id={'language'} index={3} icon={<GlobeAltIcon />} title={t('language_title')} description={t('language_text')}>
                <LanguageForm />
            </Section>
        </AccountLayout>
    );
}

const NameForm = () => {
    const t = useT(texts);
    const { account } = useAccount();
    const [first, setFirst] = useState(account?.firstName || '');
    const [last, setLast] = useState(account?.lastName || '');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, flashSaved] = useSaved();
    if (!account) return null;
    const editable = account.permissions.changeName;
    const changed = first.trim() !== account.firstName || last.trim() !== account.lastName;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editable || busy || !changed) return;
        if (!first.trim() || !last.trim()) {
            setError(t('name_required'));

            return;
        }
        setBusy(true);
        setError(null);
        updateProfile(first.trim(), last.trim())
            .then((next) => {
                setAccount(next);
                flashSaved();
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <form onSubmit={submit} className={'techtyl-account-form'}>
            <div className={'techtyl-account-row-2'}>
                <Field label={t('first_name')} value={first} onValue={setFirst} readOnly={!editable} maxLength={191} autoComplete={'given-name'} />
                <Field label={t('last_name')} value={last} onValue={setLast} readOnly={!editable} maxLength={191} autoComplete={'family-name'} />
            </div>
            <Field
                label={t('username')}
                value={account.username}
                onValue={() => undefined}
                readOnly
                mono
                hint={t('username_locked')}
                trailing={
                    <span className={'techtyl-account-input-lock'} aria-hidden>
                        <LockClosedIcon />
                    </span>
                }
            />
            {error && <Notice tone={'bad'}>{error}</Notice>}
            {editable ? (
                <div className={'techtyl-account-actions'}>
                    <Button variant={'primary'} type={'submit'} busy={busy} disabled={!changed}>
                        {t('save')}
                    </Button>
                    <Saved show={saved} />
                </div>
            ) : (
                <p className={'techtyl-account-small'}>{t('names_locked')}</p>
            )}
        </form>
    );
};

const EmailForm = () => {
    const t = useT(texts);
    const { account } = useAccount();
    const updateUserData = useStoreActions((actions: any) => actions.user.updateUserData);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, flashSaved] = useSaved();
    if (!account) return null;

    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
    const same = email.trim().toLowerCase() === account.email.toLowerCase();

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (busy) return;
        if (!valid) return setError(t('email_invalid'));
        if (same) return setError(t('email_same'));
        if (!password) return setError(t('password_needed'));
        setBusy(true);
        setError(null);
        const next = email.trim();
        updateAccountEmail(next, password)
            .then(() => {
                patchAccount({ email: next });
                updateUserData({ email: next });
                setEmail('');
                setPassword('');
                flashSaved();
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(false));
    };

    return (
        <form onSubmit={submit} className={'techtyl-account-form'} noValidate>
            <p className={'techtyl-account-current'}>
                <span>{t('email_current')}</span>
                <strong>{account.email}</strong>
            </p>
            <Field
                label={t('email_new')}
                type={'email'}
                value={email}
                onValue={setEmail}
                autoComplete={'email'}
                maxLength={191}
                error={email && !valid ? t('email_invalid') : null}
            />
            <Field label={t('current_password')} type={'password'} value={password} onValue={setPassword} autoComplete={'current-password'} reveal hint={t('email_password_hint')} />
            {error && <Notice tone={'bad'}>{error}</Notice>}
            <div className={'techtyl-account-actions'}>
                <Button variant={'primary'} type={'submit'} busy={busy} disabled={!email || !password}>
                    {t('email_save')}
                </Button>
                <Saved show={saved}>{t('email_saved')}</Saved>
            </div>
            <p className={'techtyl-account-small'}>{t('email_limit')}</p>
        </form>
    );
};

const LanguageForm = () => {
    const t = useT(texts);
    const locale = useLocale();
    const updateUserData = useStoreActions((actions: any) => actions.user.updateUserData);
    const [busy, setBusy] = useState<'en' | 'de' | null>(null);
    const [error, setError] = useState<string | null>(null);

    const choose = (language: 'en' | 'de') => {
        if (busy || language === locale) return;
        setBusy(language);
        setError(null);
        updateLanguage(language)
            .then(() => {
                // The app reads the language from the user store: switching it re-renders every page.
                updateUserData({ language });
                document.documentElement.setAttribute('lang', language);
            })
            .catch((e) => setError(explain(t, e)))
            .then(() => setBusy(null));
    };

    const options: { value: 'en' | 'de'; label: string; native: string }[] = [
        { value: 'en', label: 'English', native: t('lang_en') },
        { value: 'de', label: 'Deutsch', native: t('lang_de') },
    ];

    return (
        <div className={'techtyl-account-form'}>
            <div className={'techtyl-account-choice'} role={'radiogroup'} aria-label={t('language_title')}>
                {options.map((option) => (
                    <button
                        key={option.value}
                        type={'button'}
                        role={'radio'}
                        aria-checked={locale === option.value}
                        className={locale === option.value ? 'is-on' : ''}
                        onClick={() => choose(option.value)}
                        disabled={!!busy}
                    >
                        <span className={'techtyl-account-choice-code'} aria-hidden>
                            {option.value.toUpperCase()}
                        </span>
                        <span>
                            <strong>{option.label}</strong>
                            <small>{option.native}</small>
                        </span>
                        {busy === option.value && <span className={'techtyl-account-spinner'} aria-hidden />}
                    </button>
                ))}
            </div>
            {error && <Notice tone={'bad'}>{error}</Notice>}
            {fallbackLocale() === 'de' && <Notice tone={'info'}>{t('language_default_de')}</Notice>}
            <p className={'techtyl-account-small'}>{t('language_hint')}</p>
        </div>
    );
};
