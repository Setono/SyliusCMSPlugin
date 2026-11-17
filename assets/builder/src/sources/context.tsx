import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { usePreviewState } from '../context';
import type { JsonObject } from '../types';
import type { SourcesApi } from './api';
import type { SourceDefinition } from './sources';

/** Counts what a source yields with its parameters, right now in the preview. */
export type CountSource = (source: string, params: JsonObject) => Promise<number>;

interface Sources {
    /** The sources editors may pick, in the order the registry has them. */
    sources: readonly SourceDefinition[];
    /** Null while there's no preview to count in, or without the endpoint. */
    count: CountSource | null;
}

const SourcesContext = createContext<Sources>({ sources: [], count: null });

/** Gives list fields' controls the sources and how to count what they yield. */
export const SourcesProvider = SourcesContext.Provider;

/** The sources, and how to count what they yield. None outside a SourcesProvider. */
export function useSources(): Sources {
    return useContext(SourcesContext);
}

/**
 * Gives list fields' controls the sources, and counts what they yield under the token of the page the preview shows,
 * so in its channel, locale, Shown to and Date. It needs the builder's session, for the preview.
 */
export function PreviewSourcesProvider({ sources, api, children }: { sources: readonly SourceDefinition[]; api: SourcesApi | null; children: ReactNode }) {
    const token = usePreviewState((state) => state.token);
    const value = useMemo<Sources>(
        () => ({ sources, count: api === null || token === null ? null : (source, params) => api.count(token, source, params) }),
        [sources, api, token],
    );

    return <SourcesContext.Provider value={value}>{children}</SourcesContext.Provider>;
}

/**
 * How many items the source yields right now with the parameters: a number once counted, and null while it's counted,
 * when it can't be, or when the count failed. It's counted again when the parameters or the preview change.
 */
export function useSourceCount(source: string, params: JsonObject): number | null {
    const { count } = useSources();
    const [counted, setCounted] = useState<number | null>(null);
    // The parameters as a key, so an equal object doesn't count again
    const key = JSON.stringify(params);

    useEffect(() => {
        setCounted(null);
        if (count === null) {
            return undefined;
        }

        let active = true;
        count(source, JSON.parse(key) as JsonObject).then(
            (n) => {
                if (active) {
                    setCounted(n);
                }
            },
            () => undefined,
        );

        return () => {
            active = false;
        };
    }, [count, source, key]);

    return counted;
}
