/**
 * What the builder knows of the things it looks up by key, such as the media items, products and taxons fields hold, so
 * a field names what it holds rather than its UUID or code. Each key is looked up once, together with the others asked
 * for at the same time, and remembered from then on, also when nothing was found. A lookup that failed is asked for
 * again next time, and what the editor picks is known without one.
 */
import { useEffect } from 'react';
import { useStore } from 'zustand';
import { createStore, type StoreApi } from 'zustand/vanilla';

export interface LookupState<T> {
    /** By key: what was found, or null when nothing was, such as a deleted media item. A key not looked up yet is left out. */
    items: Readonly<Record<string, T | null>>;
    /** Looks up the keys it doesn't know yet, and isn't looking up already. */
    request(keys: readonly string[]): void;
    /** Knows these from now on, by key, such as what the editor just picked. */
    remember(items: Readonly<Record<string, T>>): void;
}

export type LookupStore<T> = StoreApi<LookupState<T>>;

/**
 * @param find what it finds of the keys, by key. A key it leaves out wasn't found
 */
export function createLookup<T>(find: (keys: string[]) => Promise<Readonly<Record<string, T>>>): LookupStore<T> {
    const pending = new Set<string>();

    return createStore<LookupState<T>>()((set, get) => ({
        items: {},

        request(keys) {
            const unknown = [...new Set(keys)].filter((key) => !(key in get().items) && !pending.has(key));
            if (unknown.length === 0) {
                return;
            }

            unknown.forEach((key) => pending.add(key));
            find(unknown).then(
                (found) => {
                    unknown.forEach((key) => pending.delete(key));
                    set((state) => ({ items: { ...state.items, ...Object.fromEntries(unknown.map((key) => [key, found[key] ?? null])) } }));
                },
                () => {
                    // Asked for again next time
                    unknown.forEach((key) => pending.delete(key));
                },
            );
        },

        remember(items) {
            set((state) => ({ items: { ...state.items, ...items } }));
        },
    }));
}

/**
 * What the lookup knows of the keys, which it looks up once, in one request for those not known yet: by key, what was
 * found, or null when nothing was. A key that's absent is being looked up. An empty key is never looked up.
 */
export function useLookup<T>(lookup: LookupStore<T>, keys: readonly string[]): Readonly<Record<string, T | null>> {
    const items = useStore(lookup, (state) => state.items);
    const wanted = keys.filter((key) => key !== '');
    const asked = JSON.stringify(wanted);

    useEffect(() => {
        const keysAsked = JSON.parse(asked) as string[];
        if (keysAsked.length > 0) {
            lookup.getState().request(keysAsked);
        }
    }, [lookup, asked]);

    const known: Record<string, T | null> = {};
    for (const key of wanted) {
        const item = items[key];
        if (item !== undefined) {
            known[key] = item;
        }
    }

    return known;
}
