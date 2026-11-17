/**
 * What pickers know of the things fields hold, by kind and ref, so a field names what it holds rather than its code or
 * UUID: looked up through the search endpoint once, in one request per kind, or remembered from a pick.
 */
import { createLookup, type LookupStore } from '../lookup';
import type { SearchApi, SearchResult } from './api';

/** By knownKey(kind, ref): what it refers to, or null when the lookup didn't find it, such as a deleted product. */
export type KnownRefsStore = LookupStore<SearchResult>;

const SEPARATOR = '\u0000';

export function knownKey(kind: string, ref: string): string {
    return `${kind}${SEPARATOR}${ref}`;
}

export function createKnownRefs(api: Pick<SearchApi, 'find'>): KnownRefsStore {
    return createLookup<SearchResult>(async (keys) => {
        const refsByKind = new Map<string, string[]>();
        for (const key of keys) {
            const [kind = '', ref = ''] = key.split(SEPARATOR);
            refsByKind.set(kind, [...(refsByKind.get(kind) ?? []), ref]);
        }

        const found: Record<string, SearchResult> = {};
        await Promise.all(
            [...refsByKind].map(async ([kind, refs]) => {
                for (const result of await api.find(kind, refs)) {
                    found[knownKey(kind, result.ref)] = result;
                }
            }),
        );

        return found;
    });
}
