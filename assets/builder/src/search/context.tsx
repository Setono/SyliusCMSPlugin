import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';
import { useStore } from 'zustand';
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
    const search = useSearch();
    const known = search?.known ?? noneKnown;
    const found = useStore(known, (state) => (ref === null ? undefined : state.items[knownKey(kind, ref)]));

    useEffect(() => {
        if (ref !== null && ref !== '') {
            known.getState().request(kind, [ref]);
        }
    }, [known, kind, ref]);

    return found ?? undefined;
}

const noneKnown = createKnownRefs({ find: async () => [] });
