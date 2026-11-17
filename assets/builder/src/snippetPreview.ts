/**
 * A snippet's preview on the real host page of one of its placements, with a sample object, such as a product, whose
 * page it is. The preview_snippet endpoint says what the top bar shows of it, and preview_samples what the sample
 * object picker finds, both under the preview's token. They're internal to the builder.
 * See "Snippet preview" in docs/spec/builder.md
 */
import { ApiError, defaultFetch, send, type Fetch } from './api';
import type { SnippetPlacement } from './config';
import type { SearchResult } from './search/api';

/** The sample object the preview shows the snippet with. */
export interface Sample {
    /** The context key it's the object of, such as product. */
    key: string;
    /** Its code, or its UUID for an entry. */
    ref: string;
    /** In the admin user's locale. */
    name: string;
    /** Whether its page is inside the placement's page scope, or null when the preview has no placement. */
    matches: boolean | null;
}

/** What the top bar shows of the snippet's preview. */
export interface SnippetPreviewState {
    /** The sample object, or null when the snippet's context has none, such as for any page. */
    sample: Sample | null;
    /** The titles of the snippets shoppers like the preview's Shown to see where the placement loses its capped slot, or null. */
    shownInstead: string[] | null;
}

/** A sample object the picker found, with whether it's inside the placement's page scope, or null without a placement. */
export interface SampleResult extends SearchResult {
    matches: boolean | null;
}

export interface SnippetPreviewApi {
    /** What the top bar shows of the preview the token turns on. */
    state(token: string): Promise<SnippetPreviewState>;
    /** The sample objects that match the query, for the preview the token turns on. An empty query finds the first page. */
    samples(token: string, query: string): Promise<SampleResult[]>;
}

/** The endpoints at the URLs the builder page gives. They change nothing, so they need no CSRF token. */
export function createSnippetPreviewApi(endpoints: { snippet: string; samples: string }, fetchFn: Fetch = defaultFetch): SnippetPreviewApi {
    return {
        async state(token) {
            const body = await send(fetchFn, `${endpoints.snippet}?${new URLSearchParams({ _cms_preview: token }).toString()}`);
            if (!isObject(body) || !(body.sample === null || isSample(body.sample)) || !(body.shownInstead === null || isTitles(body.shownInstead))) {
                throw new ApiError(`${endpoints.snippet} didn't answer with the snippet's preview.`, 200);
            }

            return { sample: body.sample, shownInstead: body.shownInstead };
        },

        async samples(token, query) {
            const body = await send(fetchFn, `${endpoints.samples}?${new URLSearchParams({ q: query, _cms_preview: token }).toString()}`);
            if (!Array.isArray(body) || !body.every(isSampleResult)) {
                throw new ApiError(`${endpoints.samples} didn't answer with sample objects.`, 200);
            }

            return body;
        },
    };
}

/** The placement a snippet's preview starts in: its first one in a slot the code still declares, or null for a plain page. */
export function startingPlacement(placements: readonly SnippetPlacement[] | null): string | null {
    return placements?.find(({ removed }) => !removed)?.uuid ?? null;
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isSample(value: unknown): value is Sample {
    return (
        isObject(value) &&
        typeof value.key === 'string' &&
        typeof value.ref === 'string' &&
        typeof value.name === 'string' &&
        (value.matches === null || typeof value.matches === 'boolean')
    );
}

function isTitles(value: unknown): value is string[] {
    return Array.isArray(value) && value.every((title) => typeof title === 'string');
}

function isSampleResult(value: unknown): value is SampleResult {
    return (
        isObject(value) &&
        typeof value.ref === 'string' &&
        typeof value.name === 'string' &&
        (value.thumbnail === null || typeof value.thumbnail === 'string') &&
        (value.matches === null || typeof value.matches === 'boolean')
    );
}
