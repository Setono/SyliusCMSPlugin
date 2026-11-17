/**
 * The source_count endpoint: how many items a source yields right now for its parameters, under a preview token, so in
 * the preview's channel, locale, Shown to and Date. It's internal to the builder.
 */
import { ApiError, defaultFetch, send, type Fetch } from '../api';
import type { JsonObject } from '../types';

export interface SourcesApi {
    /** How many items the source yields with the parameters, in the preview the token turns on. */
    count(token: string, source: string, params: JsonObject): Promise<number>;
}

/** The source_count endpoint at the URL, which the builder page gives. It changes nothing, so it needs no CSRF token. */
export function createSourcesApi(endpoint: string, fetchFn: Fetch = defaultFetch): SourcesApi {
    return {
        async count(token, source, params) {
            const body = await send(fetchFn, `${endpoint}?_cms_preview=${encodeURIComponent(token)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ source, params }),
            });
            const count = typeof body === 'object' && body !== null ? (body as Record<string, unknown>).count : undefined;
            if (typeof count !== 'number') {
                throw new ApiError(`${endpoint} didn't answer with a count.`, 200);
            }

            return count;
        },
    };
}
