import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { useLookup } from '../lookup';
import type { SearchApi, SearchResult } from './api';
import { createKnownRefs, knownKey, type KnownRefsStore } from './known';

/** What pickers search with, and what they know of the things fields hold. */
export interface Search {
    api: SearchApi;
    known: KnownRefsStore;
}

const SearchContext = createContext<Search | null>(null);

/** The search, or null where the builder has none, such as with no search endpoint in its config. */
export function useSearch(): Search | null {
    return useContext(SearchContext);
}

export function SearchProvider({ api, children }: { api: SearchApi; children: ReactNode }) {
    const search = useMemo<Search>(() => ({ api, known: createKnownRefs(api) }), [api]);

    return <SearchContext.Provider value={search}>{children}</SearchContext.Provider>;
}

/**
 * What the ref of the kind refers to, which it looks up once: the result, or undefined while it's looked up, or when
 * it wasn't found or the kind can't look refs up. Without a search, nothing is known.
 */
export function useKnownRef(kind: string, ref: string | null): SearchResult | undefined {
    const found = useKnownRefs(kind, ref === null ? [] : [ref]);

    return (ref === null ? undefined : found[ref]) ?? undefined;
}

/**
 * What each of the refs of the kind refers to, which it looks up once, in one request for those not known yet: by ref,
 * the result, or null when it wasn't found, such as a deleted product. A ref that's absent is being looked up. Without
 * a search, nothing is known.
 */
export function useKnownRefs(kind: string, refs: readonly string[]): Readonly<Record<string, SearchResult | null>> {
    const known = useLookup(useSearch()?.known ?? noneKnown, refs.filter((ref) => ref !== '').map((ref) => knownKey(kind, ref)));

    const found: Record<string, SearchResult | null> = {};
    for (const ref of refs) {
        const item = known[knownKey(kind, ref)];
        if (item !== undefined) {
            found[ref] = item;
        }
    }

    return found;
}

const noneKnown = createKnownRefs({ find: async () => [] });
