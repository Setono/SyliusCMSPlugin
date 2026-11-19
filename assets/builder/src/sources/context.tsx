import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useBuilderState, usePreviewState, useProvidedContext } from '../context';
import type { ContentTypeDefinition, JsonObject, JsonValue } from '../types';
import type { SourcesApi } from './api';
import type { SourceDefinition } from './sources';

/** Counts what a source yields with its parameters, right now in the preview. */
export type CountSource = (source: string, params: JsonObject) => Promise<number>;

/** The displays the Entries block offers for its entries field's value, right now in the preview. */
export type OfferDisplays = (entries: JsonValue | null) => Promise<string[]>;

interface Sources {
    /** The sources editors may pick, in the order the registry has them. */
    sources: readonly SourceDefinition[];
    /** Null while there's no preview to count in, or without the endpoint. */
    count: CountSource | null;
    /**
     * The context keys the edited entry provides or the snippet declares: a source is offered only where it provides
     * all a source needs. Without them, outside the builder, every source is offered.
     */
    provided?: readonly string[] | undefined;
    /**
     * The edited entry's content type, which a source that fits only some content types as the entry must fit, such as
     * In this category. Null in a snippet, whose entry can be of any, and undefined outside the builder.
     */
    entryContentType?: string | null | undefined;
    /** Each content type's category content type, by code, whose entries From a collection's categories lists. */
    categoryTypes?: Readonly<Record<string, string | null>> | undefined;
    /** Null or absent while there's no preview to ask in, or without the endpoint: every display is offered. */
    displays?: OfferDisplays | null | undefined;
}

const SourcesContext = createContext<Sources>({ sources: [], count: null });

/** Gives list fields' controls the sources and how to count what they yield. */
export const SourcesProvider = SourcesContext.Provider;

/** The sources, and how to count what they yield. None outside a SourcesProvider. */
export function useSources(): Sources {
    return useContext(SourcesContext);
}

/**
 * Gives list fields' controls the sources, with the context the edited entry provides and its content type, which
 * narrow those offered, and counts what they yield under the token of the page the preview shows, so in its channel,
 * locale, Shown to and Date. It needs the builder's session, for the entry and the preview.
 */
export function PreviewSourcesProvider({
    sources,
    api,
    contentTypes = [],
    children,
}: {
    sources: readonly SourceDefinition[];
    api: SourcesApi | null;
    contentTypes?: readonly ContentTypeDefinition[];
    children: ReactNode;
}) {
    const token = usePreviewState((state) => state.token);
    const provided = useProvidedContext() ?? undefined;
    // A snippet provides no context of its own, so its entry is the host page's, of any content type
    const entryContentType = useBuilderState((state) => (state.rules.provides === null ? null : state.entry.contentType));
    const categoryTypes = useMemo(() => Object.fromEntries(contentTypes.map(({ code, categoryType }) => [code, categoryType ?? null])), [contentTypes]);
    const value = useMemo<Sources>(
        () => ({
            sources,
            count: api === null || token === null ? null : (source, params) => api.count(token, source, params),
            provided,
            entryContentType,
            categoryTypes,
            displays: api?.displays === undefined || token === null ? null : (entries) => api.displays?.(token, entries) ?? Promise.resolve([]),
        }),
        [sources, api, token, provided, entryContentType, categoryTypes],
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

/**
 * The displays the Entries block offers right now for its entries field's value: those the listed content type has
 * cards for, once the server answers, and null while it's asked, when it can't be, or when asking failed. It's asked
 * again when the value or the preview change. Pass enabled false for a block that isn't an Entries block.
 */
export function useOfferedDisplays(entries: JsonValue | undefined, enabled: boolean): string[] | null {
    const { displays } = useSources();
    const [offered, setOffered] = useState<string[] | null>(null);
    // The value as a key, so an equal value doesn't ask again
    const key = JSON.stringify(entries ?? null);

    useEffect(() => {
        setOffered(null);
        if (!enabled || displays === null || displays === undefined) {
            return undefined;
        }

        let active = true;
        displays(JSON.parse(key) as JsonValue).then(
            (answered) => {
                if (active) {
                    setOffered(answered);
                }
            },
            () => undefined,
        );

        return () => {
            active = false;
        };
    }, [displays, enabled, key]);

    return offered;
}
