import { useEffect, useState } from 'react';
import { AccountData, getAccount } from './api';
import { publishOwnAvatar } from './Avatar';

/* Gemeinsamer Stand der Kontoseiten: einmal laden, beim Reiterwechsel sofort da. */

let current: AccountData | null = null;
let loading: Promise<AccountData> | null = null;
let failure: unknown = null;
const listeners = new Set<() => void>();

const notify = () => listeners.forEach((listener) => listener());

export const setAccount = (next: AccountData) => {
    current = next;
    failure = null;
    publishOwnAvatar(next.avatar);
    notify();
};

export const patchAccount = (changes: Partial<AccountData>) => {
    if (!current) return;
    current = { ...current, ...changes };
    if (changes.avatar) publishOwnAvatar(changes.avatar);
    notify();
};

export const reloadAccount = (): Promise<AccountData> => {
    const run = getAccount().then(
        (data) => {
            loading = null;
            setAccount(data);

            return data;
        },
        (error) => {
            loading = null;
            failure = error;
            notify();
            throw error;
        }
    );
    loading = run;

    return run;
};

export const useAccount = () => {
    const [, setTick] = useState(0);

    useEffect(() => {
        const listener = () => setTick((value) => value + 1);
        listeners.add(listener);
        if (!current && !loading) reloadAccount().catch(() => undefined);

        return () => {
            listeners.delete(listener);
        };
    }, []);

    return { account: current, error: failure, reload: reloadAccount };
};
