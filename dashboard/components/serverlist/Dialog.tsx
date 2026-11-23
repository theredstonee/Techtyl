import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

interface DialogProps {
    title: string;
    text?: string;
    confirmLabel: string;
    cancelLabel: string;
    danger?: boolean;
    busy?: boolean;
    error?: string;
    onConfirm: () => void;
    onClose: () => void;
    children?: React.ReactNode;
}

// Small confirmation layer. Focus starts on Cancel so a stray Enter does not confirm.
export default ({ title, text, confirmLabel, cancelLabel, danger, busy, error, onConfirm, onClose, children }: DialogProps) => {
    const box = useRef<HTMLDivElement>(null);
    const cancel = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const node = cancel.current;
        if (node) node.focus();
    }, []);

    const onKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            event.nativeEvent.stopImmediatePropagation();
            if (!busy) onClose();
            return;
        }
        if (event.key !== 'Tab' || !box.current) return;
        const nodes = box.current.querySelectorAll('button, [href], input');
        const items: HTMLElement[] = [];
        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            if (node instanceof HTMLElement && !node.hasAttribute('disabled')) items.push(node);
        }
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
    };

    return createPortal(
        <div
            className={'techtyl-toolbar-backdrop'}
            onMouseDown={(event) => {
                if (event.target === event.currentTarget && !busy) onClose();
            }}
        >
            <div
                className={'techtyl-toolbar-dialog'}
                role={'dialog'}
                aria-modal={'true'}
                aria-labelledby={'techtyl-toolbar-dialog-title'}
                aria-describedby={text ? 'techtyl-toolbar-dialog-text' : undefined}
                ref={box}
                onKeyDown={onKeyDown}
            >
                <h2 id={'techtyl-toolbar-dialog-title'}>{title}</h2>
                {text && (
                    <p id={'techtyl-toolbar-dialog-text'}>{text}</p>
                )}
                {children}
                {error && (
                    <p className={'techtyl-toolbar-notice is-bad'} role={'alert'}>
                        {error}
                    </p>
                )}
                <div className={'techtyl-toolbar-dialog-actions'}>
                    <button type={'button'} className={'techtyl-toolbar-btn'} onClick={onClose} disabled={busy} ref={cancel}>
                        {cancelLabel}
                    </button>
                    <button
                        type={'button'}
                        className={'techtyl-toolbar-btn' + (danger ? ' techtyl-toolbar-danger' : ' techtyl-toolbar-primary')}
                        onClick={onConfirm}
                        disabled={busy}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
};
