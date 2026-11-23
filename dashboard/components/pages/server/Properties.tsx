import React, { useEffect, useMemo, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { explain, getProperties, PropertiesView, PropertyField, saveProperties, sendPower, ToolsMeta } from '../../tools/api';
import { useToolsT } from '../../tools/text';
import { Button, Dialog, Empty, Icon, Notice, SkeletonRows, Toasts, ToolsGate, useToasts } from '../../tools/ui';

const GROUPS = ['game', 'world', 'perf', 'net', 'remote', 'packs', 'custom'];

const copyOf = (fields: PropertyField[]): Record<string, string> => {
    const next: Record<string, string> = {};
    fields.forEach((field) => {
        next[field.key] = field.value;
    });

    return next;
};

interface RowProps {
    field: PropertyField;
    value: string;
    canWrite: boolean;
    onChange: (key: string, value: string) => void;
}

const FieldRow = ({ field, value, canWrite, onChange }: RowProps) => {
    const t = useToolsT();
    const [reveal, setReveal] = useState(false);
    const hintId = 'prop-hint-' + field.key;
    const wide = field.type === 'text' && ((field.max || 0) >= 512 || field.key === 'generator-settings');
    const options = field.options || [];
    const extra = field.type === 'enum' && value !== '' && options.indexOf(value) === -1;

    let control: React.ReactNode;
    if (field.type === 'bool') {
        const on = value.toLowerCase() === 'true';
        control = (
            <div>
                <button
                    type={'button'}
                    role={'switch'}
                    className={'techtyl-tools-switch'}
                    aria-checked={on}
                    aria-label={field.key}
                    disabled={!canWrite}
                    onClick={() => onChange(field.key, on ? 'false' : 'true')}
                >
                    <span />
                </button>
                <span className={'techtyl-tools-muted'}> {on ? t('props_on') : t('props_off')}</span>
                {value !== 'true' && value !== 'false' && <div className={'techtyl-tools-prop-hint'}>{t('props_file_value', { value })}</div>}
            </div>
        );
    } else if (field.type === 'enum') {
        control = (
            <div className={'techtyl-tools-field'}>
                <select aria-label={field.key} aria-describedby={hintId} value={value} disabled={!canWrite} onChange={(event) => onChange(field.key, event.target.value)}>
                    {extra && <option value={value}>{value}</option>}
                    {options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>
        );
    } else if (field.secret) {
        control = (
            <div className={'techtyl-tools-field techtyl-tools-secret'}>
                <input
                    type={reveal ? 'text' : 'password'}
                    value={value}
                    maxLength={Math.max(field.max || 256, value.length)}
                    autoComplete={'off'}
                    spellCheck={false}
                    aria-label={field.key}
                    aria-describedby={hintId}
                    disabled={!canWrite}
                    onChange={(event) => onChange(field.key, event.target.value)}
                />
                <Button small look={'ghost'} aria-pressed={reveal} onClick={() => setReveal((open) => !open)}>
                    {reveal ? t('props_hide_secret') : t('props_show_secret')}
                </Button>
            </div>
        );
    } else if (wide) {
        control = (
            <div className={'techtyl-tools-field'}>
                <textarea
                    value={value}
                    maxLength={Math.max(field.max || 8192, value.length)}
                    aria-label={field.key}
                    aria-describedby={hintId}
                    disabled={!canWrite}
                    spellCheck={false}
                    onChange={(event) => onChange(field.key, event.target.value)}
                />
            </div>
        );
    } else {
        const cap = field.type === 'int' ? Math.max(20, value.length) : Math.max(field.max || 1024, value.length);
        control = (
            <div className={'techtyl-tools-field'}>
                <input
                    type={'text'}
                    inputMode={field.type === 'int' ? 'numeric' : 'text'}
                    value={value}
                    maxLength={cap}
                    aria-label={field.key}
                    aria-describedby={hintId}
                    disabled={!canWrite}
                    autoComplete={'off'}
                    spellCheck={false}
                    onChange={(event) => onChange(field.key, event.target.value)}
                />
            </div>
        );
    }

    return (
        <div className={'techtyl-tools-prop'}>
            <div>
                <div className={'techtyl-tools-prop-key'}>
                    {field.key}
                    {!field.present && <span className={'techtyl-tools-pill'}>{t('props_not_in_file')}</span>}
                </div>
                <p className={'techtyl-tools-prop-hint'} id={hintId}>
                    {field.hint}
                </p>
            </div>
            {control}
        </div>
    );
};

const Page = ({ meta, server }: { meta: ToolsMeta; server: string }) => {
    const t = useToolsT();
    const { toasts, push, dismiss } = useToasts();
    const canRead = !!meta.permissions.read_content;
    const canWrite = !!meta.permissions.update_files;
    const [data, setData] = useState<PropertiesView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [draft, setDraft] = useState<Record<string, string>>({});
    const [saved, setSaved] = useState<Record<string, string>>({});
    const [query, setQuery] = useState('');
    const [opened, setOpened] = useState<Record<string, boolean>>({ game: true, custom: true });
    const [busy, setBusy] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const [askRestart, setAskRestart] = useState(false);
    const [restarting, setRestarting] = useState(false);

    useEffect(() => {
        if (!canRead) return undefined;
        let alive = true;
        setError(null);
        getProperties(server)
            .then((view) => {
                if (!alive) return;
                const next = copyOf(view.fields);
                setData(view);
                setDraft(next);
                setSaved(next);
            })
            .catch((problem) => alive && setError(explain(problem)));

        return () => {
            alive = false;
        };
    }, [server, attempt, canRead]);

    const needle = query.trim().toLowerCase();
    const grouped = useMemo(() => {
        const buckets: Record<string, PropertyField[]> = {};
        GROUPS.forEach((group) => {
            buckets[group] = [];
        });
        (data ? data.fields : []).forEach((field) => {
            if (needle !== '' && field.key.toLowerCase().indexOf(needle) === -1 && field.hint.toLowerCase().indexOf(needle) === -1) {
                return;
            }
            const group = buckets[field.group] ? field.group : 'custom';
            buckets[group].push(field);
        });

        return buckets;
    }, [data, needle]);

    const dirty = useMemo(() => {
        const changes: Record<string, string> = {};
        Object.keys(draft).forEach((key) => {
            if (draft[key] !== saved[key]) changes[key] = draft[key];
        });

        return changes;
    }, [draft, saved]);
    const dirtyCount = Object.keys(dirty).length;

    const store = async (create: boolean) => {
        setBusy(true);
        try {
            const result = await saveProperties(server, create ? dirty : dirty, create);
            push('ok', result.created ? t('props_created') : t('props_saved'));
            setAttempt((value) => value + 1);
            if (meta.permissions.restart) setAskRestart(true);
        } catch (problem) {
            push('danger', explain(problem));
        }
        setBusy(false);
    };

    const restart = async () => {
        setRestarting(true);
        try {
            await sendPower(server, 'restart');
            setAskRestart(false);
            push('ok', t('restart_started'));
        } catch (problem) {
            push('danger', explain(problem, t('restart_failed')));
        }
        setRestarting(false);
    };

    if (!canRead) {
        return (
            <Empty icon={'warn'} title={t('need_content_title')}>
                {t('need_content_text')}
            </Empty>
        );
    }

    return (
        <>
            <Toasts toasts={toasts} dismiss={dismiss} />
            <div className={'techtyl-tools-top'}>
                <p>{t('props_intro')}</p>
                <Button small look={'ghost'} onClick={() => setAttempt((value) => value + 1)} disabled={busy}>
                    <Icon id={'refresh'} size={16} />
                    {t('refresh')}
                </Button>
            </div>
            <Notice tone={'warning'} title={t('props_restart_title')}>
                {t('props_restart_text')}
            </Notice>
            {!canWrite && (
                <div style={{ marginTop: 12 }}>
                    <Notice tone={'info'}>{t('props_no_write')}</Notice>
                </div>
            )}
            {error && (
                <div style={{ marginTop: 12 }}>
                    <Empty icon={'warn'} title={t('load_failed')} action={<Button onClick={() => setAttempt((value) => value + 1)}>{t('retry')}</Button>}>
                        {error}
                    </Empty>
                </div>
            )}
            {!error && !data && (
                <div className={'techtyl-tools-panel'} style={{ marginTop: 12 }}>
                    <SkeletonRows rows={6} />
                </div>
            )}
            {data && !data.exists && (
                <div style={{ marginTop: 12 }}>
                    <Empty icon={'box'} title={t('props_missing_title')}>
                        {t('props_missing_text')}
                    </Empty>
                </div>
            )}
            {data && data.odd > 0 && (
                <div style={{ marginTop: 12 }}>
                    <Notice tone={'info'}>{t('props_odd', { count: data.odd })}</Notice>
                </div>
            )}
            {data && (
                <div className={'techtyl-tools-panel'} style={{ marginTop: 12 }}>
                    <div className={'techtyl-tools-search'} style={{ marginBottom: 8 }}>
                        <Icon id={'search'} size={18} />
                        <input
                            type={'search'}
                            value={query}
                            maxLength={80}
                            placeholder={t('props_search')}
                            aria-label={t('props_search')}
                            autoComplete={'off'}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    {GROUPS.every((group) => grouped[group].length === 0) && <p className={'techtyl-tools-muted'}>{t('props_filter_empty')}</p>}
                    {GROUPS.map((group) => {
                        const rows = grouped[group];
                        if (rows.length === 0) return null;
                        const open = needle !== '' ? true : opened[group] === true;

                        return (
                            <details
                                key={group}
                                className={'techtyl-tools-fold'}
                                open={open}
                                onToggle={(event) => {
                                    if (needle !== '') return;
                                    const isOpen = event.currentTarget.open;
                                    setOpened((prev) => ({ ...prev, [group]: isOpen }));
                                }}
                            >
                                <summary>
                                    {t('props_group_' + group)}
                                    <span className={'techtyl-tools-muted'}>{rows.length}</span>
                                </summary>
                                {rows.map((field) => (
                                    <FieldRow
                                        key={field.key}
                                        field={field}
                                        value={draft[field.key] != null ? draft[field.key] : field.value}
                                        canWrite={canWrite && !busy}
                                        onChange={(key, next) => setDraft((prev) => ({ ...prev, [key]: next }))}
                                    />
                                ))}
                            </details>
                        );
                    })}
                    {canWrite && (dirtyCount > 0 || !data.exists) && (
                        <div className={'techtyl-tools-savebar'}>
                            <span>{data.exists ? t('props_dirty', { count: dirtyCount }) : t('props_missing_title')}</span>
                            <div className={'techtyl-tools-savebar-actions'}>
                                {dirtyCount > 0 && (
                                    <Button look={'ghost'} disabled={busy} onClick={() => setDraft(saved)}>
                                        {t('props_discard')}
                                    </Button>
                                )}
                                <Button look={'primary'} busy={busy} onClick={() => store(!data.exists)}>
                                    {data.exists ? t('props_save') : t('props_create')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
            {askRestart && (
                <Dialog
                    title={t('props_restart_ask')}
                    onClose={() => setAskRestart(false)}
                    footer={
                        <>
                            <Button look={'ghost'} onClick={() => setAskRestart(false)}>
                                {t('later')}
                            </Button>
                            <Button look={'primary'} busy={restarting} onClick={restart}>
                                {t('restart_now')}
                            </Button>
                        </>
                    }
                >
                    <p>{t('props_restart_ask_text')}</p>
                </Dialog>
            )}
        </>
    );
};

export default () => {
    const t = useToolsT();

    return (
        <ServerContentBlock title={t('page_properties')}>
            <ToolsGate tool={'properties'}>{(meta, server) => <Page meta={meta} server={server} />}</ToolsGate>
        </ServerContentBlock>
    );
};
