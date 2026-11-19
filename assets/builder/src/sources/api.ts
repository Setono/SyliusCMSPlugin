/**
 * The source_count endpoint: how many items a source yields right now for its parameters, under a preview token, so in
 * the preview's channel, locale, Shown to and Date, and the card_displays endpoint: the displays the Entries block
 * offers for what its entries field holds, on the page the preview shows. They're internal to the builder.
 */
import { ApiError, defaultFetch, send, type Fetch } from '../api';
import type { JsonObject, JsonValue } from '../types';

export interface SourcesApi {
    /** How many items the source yields with the parameters, in the preview the token turns on. */
    count(token: string, source: string, params: JsonObject): Promise<number>;
    /**
     * The displays the Entries block offers for its entries field's value, hand-picked or a source, in the preview the
     * token turns on. Absent without the endpoint, when every display is offered.
     */
    displays?(token: string, entries: JsonValue | null): Promise<string[]>;
}

/**
 * The source_count endpoint at the URL, which the builder page gives, and the card_displays endpoint when it gives that
 * too. They change nothing, so they need no CSRF token.
 */
export function createSourcesApi(endpoint: string, fetchFn: Fetch = defaultFetch, displaysEndpoint?: string): SourcesApi {
    const post = (url: string, token: string, body: JsonValue): Promise<unknown> =>
        send(fetchFn, `${url}?_cms_preview=${encodeURIComponent(token)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

    const api: SourcesApi = {
        async count(token, source, params) {
            const body = await post(endpoint, token, { source, params });
            const count = typeof body === 'object' && body !== null ? (body as Record<string, unknown>).count : undefined;
            if (typeof count !== 'number') {
                throw new ApiError(`${endpoint} didn't answer with a count.`, 200);
            }

            return count;
        },
    };

    if (displaysEndpoint !== undefined) {
        api.displays = async (token, entries) => {
            const body = await post(displaysEndpoint, token, { entries });
            const displays = typeof body === 'object' && body !== null ? (body as Record<string, unknown>).displays : undefined;
            if (!Array.isArray(displays)) {
                throw new ApiError(`${displaysEndpoint} didn't answer with the displays.`, 200);
            }

            return displays.filter((display): display is string => typeof display === 'string');
        };
    }

    return api;
}
