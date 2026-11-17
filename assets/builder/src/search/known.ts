/**
 * What pickers know of the things fields hold, by kind and ref, so a field names what it holds rather than its code or
 * UUID: looked up through the search endpoint once, in one request per kind, or remembered from a pick.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { SearchApi, SearchResult } from './api';

export interface KnownRefsState {
    /** By knownKey(kind, ref): what it refers to, or null when the lookup didn't find it, such as a deleted product. */
    items: Readonly<Record<string, SearchResult | null>>;
    /** Looks up the refs of the kind that aren't known or being looked up yet. */
    request(kind: string, refs: readonly string[]): void;
    /** Remembers what a search found, such as what the editor picked. */
    remember(kind: string, results: readonly SearchResult[]): void;
}

export type KnownRefsStore = StoreApi<KnownRefsState>;

export function knownKey(kind: string, ref: string): string {
    return `${kind}\u0000${ref}`;
}

export function createKnownRefs(api: Pick<SearchApi, 'find'>): KnownRefsStore {
    const asked = new Set<string>();

    return createStore<KnownRefsState>()((set) => ({
        items: {},

        request(kind, refs) {
            const wanted = [...new Set(refs)].filter((ref) => !asked.has(knownKey(kind, ref)));
            if (wanted.length === 0) {
                return;
            }

            wanted.forEach((ref) => asked.add(knownKey(kind, ref)));
            api.find(kind, wanted).then(
                (found) =>
                    set(({ items }) => {
                        const next = { ...items };
                        for (const ref of wanted) {
                            next[knownKey(kind, ref)] = found.find((result) => result.ref === ref) ?? null;
                        }

                        return { items: next };
                    }),
                // Asked again next time
                () => wanted.forEach((ref) => asked.delete(knownKey(kind, ref))),
            );
        },

        remember(kind, results) {
            results.forEach(({ ref }) => asked.add(knownKey(kind, ref)));
            set(({ items }) => ({ items: { ...items, ...Object.fromEntries(results.map((result) => [knownKey(kind, result.ref), result])) } }));
        },
    }));
}
