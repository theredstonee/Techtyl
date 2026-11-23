import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useT } from '@blueprint/extensions/techtyl/i18n';
import texts from '@blueprint/extensions/techtyl/i18n/account';
import { CheckIcon, ClipboardCopyIcon, ExclamationIcon, EyeIcon, EyeOffIcon, InformationCircleIcon, XIcon } from '@heroicons/react/outline';

/* Kleine Bausteine der Kontoseiten. Klassen mit Praefix techtyl-account-, Regeln in styles.css. */

const cx = (...names: (string | false | null | undefined)[]) => names.filter(Boolean).join(' ');

/* ------------------------------------------------------------------ Gliederung */

interface SectionProps {
    id?: string;
    title: string;
    description?: React.ReactNode;
    icon?: React.ReactNode;
    children: React.ReactNode;
    index?: number;
    tone?: 'danger';
}

/** Einstellungsbereich: links Titel und Erklaerung, rechts die Karte. Auf schmalen Bildschirmen untereinander. */
export const Section = ({ id, title, description, icon, children, index = 0, tone }: SectionProps) => (
    <section
        id={id}
        className={cx('techtyl-account-section', tone === 'danger' && 'techtyl-account-section--danger')}
        style={{ ['--i' as any]: index }}
        aria-labelledby={id ? `${id}-title` : undefined}
    >
        <header className={'techtyl-account-section-head'}>
            {icon && <span className={'techtyl-account-section-icon'}>{icon}</span>}
            <div>
                <h2 id={id ? `${id}-title` : undefined}>{title}</h2>
                {description && <p>{description}</p>}
            </div>
        </header>
        <div className={'techtyl-account-card'}>{children}</div>
    </section>
);

export const Card = ({ className, children, index = 0 }: { className?: string; children: React.ReactNode; index?: number }) => (
    <div className={cx('techtyl-account-card', 'techtyl-account-rise', className)} style={{ ['--i' as any]: index }}>
        {children}
    </div>
);

/* ------------------------------------------------------------------ Knoepfe */

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'ghost' | 'danger' | 'quiet';
    busy?: boolean;
    small?: boolean;
    icon?: React.ReactNode;
}

export const Button = ({ variant = 'ghost', busy, small, icon, className, children, disabled, type = 'button', ...rest }: ButtonProps) => (
    <button
        type={type}
        className={cx('techtyl-account-btn', `techtyl-account-btn--${variant}`, small && 'techtyl-account-btn--small', busy && 'is-busy', className)}
        disabled={disabled || busy}
        aria-busy={busy || undefined}
        {...rest}
    >
        {busy ? <span className={'techtyl-account-spinner'} aria-hidden /> : icon}
        <span>{children}</span>
    </button>
);

/* ------------------------------------------------------------------ Felder */

interface FieldProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    label: string;
    hint?: React.ReactNode;
    error?: string | null;
    onValue: (value: string) => void;
    mono?: boolean;
    reveal?: boolean;
    trailing?: React.ReactNode;
}

let fieldCounter = 0;
const useFieldId = (prefix: string) => {
    const ref = useRef<string>();
    if (!ref.current) ref.current = `${prefix}-${++fieldCounter}`;

    return ref.current;
};

export const Field = ({ label, hint, error, onValue, mono, reveal, trailing, type = 'text', className, id, ...rest }: FieldProps) => {
    const autoId = useFieldId('techtyl-account-field');
    const fieldId = id || autoId;
    const [visible, setVisible] = useState(false);
    const t = useT(texts);
    const describedBy = [hint ? `${fieldId}-hint` : '', error ? `${fieldId}-error` : ''].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cx('techtyl-account-field', error && 'has-error', className)}>
            <label htmlFor={fieldId}>{label}</label>
            <div className={'techtyl-account-input'}>
                <input
                    id={fieldId}
                    type={reveal && visible ? 'text' : type}
                    className={mono ? 'is-mono' : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    onChange={(event) => onValue(event.currentTarget.value)}
                    {...rest}
                />
                {reveal && (
                    <button
                        type={'button'}
                        className={'techtyl-account-input-action'}
                        onClick={() => setVisible((value) => !value)}
                        aria-label={visible ? t('hide_password') : t('show_password')}
                        aria-pressed={visible}
                    >
                        {visible ? <EyeOffIcon /> : <EyeIcon />}
                    </button>
                )}
                {trailing}
            </div>
            {error ? (
                <p id={`${fieldId}-error`} className={'techtyl-account-field-error'} role={'alert'}>
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={`${fieldId}-hint`} className={'techtyl-account-field-hint'}>
                        {hint}
                    </p>
                )
            )}
        </div>
    );
};

interface AreaProps extends Omit<React.TextareaHTMLAttributes<HTMLTextAreaElement>, 'onChange'> {
    label: string;
    hint?: React.ReactNode;
    error?: string | null;
    onValue: (value: string) => void;
    mono?: boolean;
}

export const Area = ({ label, hint, error, onValue, mono, id, ...rest }: AreaProps) => {
    const autoId = useFieldId('techtyl-account-area');
    const fieldId = id || autoId;

    return (
        <div className={cx('techtyl-account-field', error && 'has-error')}>
            <label htmlFor={fieldId}>{label}</label>
            <div className={'techtyl-account-input'}>
                <textarea
                    id={fieldId}
                    className={mono ? 'is-mono' : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined}
                    onChange={(event) => onValue(event.currentTarget.value)}
                    {...rest}
                />
            </div>
            {error ? (
                <p id={`${fieldId}-error`} className={'techtyl-account-field-error'} role={'alert'}>
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={`${fieldId}-hint`} className={'techtyl-account-field-hint'}>
                        {hint}
                    </p>
                )
            )}
        </div>
    );
};

/* ------------------------------------------------------------------ Rueckmeldungen */

interface NoticeProps {
    tone?: 'info' | 'ok' | 'warn' | 'bad';
    title?: string;
    children?: React.ReactNode;
    action?: React.ReactNode;
    className?: string;
}

export const Notice = ({ tone = 'info', title, children, action, className }: NoticeProps) => (
    <div className={cx('techtyl-account-notice', `techtyl-account-notice--${tone}`, className)} role={tone === 'bad' ? 'alert' : 'status'}>
        <span className={'techtyl-account-notice-icon'} aria-hidden>
            {tone === 'ok' ? <CheckIcon /> : tone === 'info' ? <InformationCircleIcon /> : <ExclamationIcon />}
        </span>
        <div className={'techtyl-account-notice-body'}>
            {title && <strong>{title}</strong>}
            {children && <div>{children}</div>}
        </div>
        {action && <div className={'techtyl-account-notice-action'}>{action}</div>}
    </div>
);

/** Kurze Erfolgsmeldung, die nach einigen Sekunden verschwindet. */
export const Saved = ({ show, children }: { show: boolean; children?: React.ReactNode }) => {
    const t = useT(texts);

    return (
        <span className={cx('techtyl-account-saved', show && 'is-on')} role={'status'} aria-live={'polite'}>
            {show && (
                <>
                    <CheckIcon aria-hidden /> {children || t('saved')}
                </>
            )}
        </span>
    );
};

/** Zustand fuer <Saved>: flash() zeigt die Meldung fuer 3,5 Sekunden. */
export const useSaved = (): [boolean, () => void] => {
    const [show, setShow] = useState(false);
    const timer = useRef<number>();
    useEffect(() => () => window.clearTimeout(timer.current), []);

    return [
        show,
        () => {
            setShow(true);
            window.clearTimeout(timer.current);
            timer.current = window.setTimeout(() => setShow(false), 3500);
        },
    ];
};

/* ------------------------------------------------------------------ Anzeigen */

export const Meter = ({ percent, tone, label }: { percent: number; tone?: 'ok' | 'warn' | 'bad'; label?: string }) => (
    <div
        className={cx('techtyl-account-meter', tone && `techtyl-account-meter--${tone}`)}
        role={'meter'}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={percent}
        aria-label={label}
    >
        <i style={{ width: `${Math.max(0, Math.min(100, percent))}%` }} />
    </div>
);

/** Ring mit Prozentwert in der Mitte. */
export const Ring = ({ percent, size = 76, label }: { percent: number; size?: number; label: string }) => {
    const radius = 15.5;
    const length = 2 * Math.PI * radius;
    const value = Math.max(0, Math.min(100, percent));
    const tone = value >= 100 ? 'ok' : value >= 50 ? 'warn' : 'bad';

    return (
        <div className={cx('techtyl-account-ring', `techtyl-account-ring--${tone}`)} style={{ width: size, height: size }}>
            <svg viewBox={'0 0 36 36'} aria-hidden>
                <circle cx={18} cy={18} r={radius} className={'techtyl-account-ring-track'} />
                <circle
                    cx={18}
                    cy={18}
                    r={radius}
                    className={'techtyl-account-ring-value'}
                    strokeDasharray={`${length} ${length}`}
                    style={{ ['--techtyl-ring-to' as any]: `${length * (1 - value / 100)}`, ['--techtyl-ring-from' as any]: `${length}` }}
                />
            </svg>
            <span>
                <b>{value}&nbsp;%</b>
                <small>{label}</small>
            </span>
        </div>
    );
};

export const Skeleton = ({ lines = 3 }: { lines?: number }) => (
    <div className={'techtyl-account-skeleton'} aria-busy>
        {Array.from({ length: lines }).map((_, index) => (
            <i key={index} style={{ width: `${92 - index * 17}%` }} />
        ))}
    </div>
);

export const Empty = ({ icon, title, children, action }: { icon?: React.ReactNode; title: string; children?: React.ReactNode; action?: React.ReactNode }) => (
    <div className={'techtyl-account-empty'}>
        {icon && <span className={'techtyl-account-empty-icon'}>{icon}</span>}
        <strong>{title}</strong>
        {children && <p>{children}</p>}
        {action}
    </div>
);

/** Wert mit Kopier-Knopf. */
export const CopyValue = ({ value, label, secret }: { value: string; label: string; secret?: boolean }) => {
    const [copied, setCopied] = useState(false);
    const t = useT(texts);
    const copy = () => {
        const done = () => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done, () => fallbackCopy(value) && done());
        } else if (fallbackCopy(value)) {
            done();
        }
    };

    return (
        <div className={cx('techtyl-account-copy', secret && 'is-secret')}>
            <code aria-label={label}>{value}</code>
            <button type={'button'} onClick={copy} aria-label={t('copy_label', { label })}>
                {copied ? <CheckIcon aria-hidden /> : <ClipboardCopyIcon aria-hidden />}
                <span>{copied ? t('copied') : t('copy')}</span>
            </button>
        </div>
    );
};

/** Kopieren ohne Clipboard-API (z. B. http ohne TLS im lokalen Netz). */
export const fallbackCopy = (value: string): boolean => {
    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch (e) {
        ok = false;
    }
    document.body.removeChild(area);

    return ok;
};

/* ------------------------------------------------------------------ Dialog */

interface ModalProps {
    open: boolean;
    title: string;
    description?: React.ReactNode;
    onClose: () => void;
    children: React.ReactNode;
    footer?: React.ReactNode;
    /** Waehrend einer laufenden Aktion nicht schliessbar. */
    locked?: boolean;
    wide?: boolean;
    tone?: 'danger';
}

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export const Modal = ({ open, title, description, onClose, children, footer, locked, wide, tone }: ModalProps) => {
    const panel = useRef<HTMLDivElement>(null);
    const titleId = useFieldId('techtyl-account-modal');
    const [closing, setClosing] = useState(false);
    const [shown, setShown] = useState(open);
    const t = useT(texts);

    useEffect(() => {
        if (open) {
            setShown(true);
            setClosing(false);
        } else if (shown) {
            setClosing(true);
            const timer = window.setTimeout(() => {
                setShown(false);
                setClosing(false);
            }, 180);

            return () => window.clearTimeout(timer);
        }

        return undefined;
    }, [open]);

    useEffect(() => {
        if (!open) return undefined;
        const previous = document.activeElement as HTMLElement | null;
        const timer = window.setTimeout(() => {
            const target = panel.current?.querySelector<HTMLElement>('[data-autofocus]') || panel.current?.querySelector<HTMLElement>(FOCUSABLE);
            target?.focus();
        }, 30);
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !locked) {
                event.stopPropagation();
                onClose();
            }
            if (event.key === 'Tab' && panel.current) {
                const items = Array.from(panel.current.querySelectorAll<HTMLElement>(FOCUSABLE));
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener('keydown', onKey, true);
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            window.clearTimeout(timer);
            document.removeEventListener('keydown', onKey, true);
            document.body.style.overflow = overflow;
            previous?.focus?.();
        };
    }, [open, locked]);

    if (!shown) return null;

    return createPortal(
        <div className={cx('techtyl-account-overlay', closing && 'is-closing')} onMouseDown={(event) => event.target === event.currentTarget && !locked && onClose()}>
            <div
                ref={panel}
                className={cx('techtyl-account-modal', wide && 'is-wide', tone === 'danger' && 'is-danger')}
                role={'dialog'}
                aria-modal
                aria-labelledby={titleId}
            >
                <header>
                    <div>
                        <h2 id={titleId}>{title}</h2>
                        {description && <p>{description}</p>}
                    </div>
                    <button type={'button'} className={'techtyl-account-modal-close'} onClick={onClose} disabled={locked} aria-label={t('close')}>
                        <XIcon aria-hidden />
                    </button>
                </header>
                <div className={'techtyl-account-modal-body'}>{children}</div>
                {footer && <footer>{footer}</footer>}
            </div>
        </div>,
        document.body
    );
};
