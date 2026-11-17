/**
 * The search endpoint pickers search with: products, taxons, entries, customer groups and what each link target refers
 * to, by kind. It's internal, but what field controls reach through `.builder.search()` keeps its shape
 * (docs/spec/extending.md#field-controls).
 */
import { ApiError } from '../api';

/** One thing a search found: what a field stores to refer to it, its name in the admin user's locale and its thumbnail. */
export interface SearchResult {
    ref: string;
    name: string;
    thumbnail: string | null;
}

export interface SearchApi {
    /** What of the kind matches the query, best first, at most a page of it. An empty query finds the first page. */
    search(kind: string, query: string): Promise<SearchResult[]>;
    /** Those of the refs of the kind that exist, so a picker can name what a field holds. A kind can't always look them up. */
    find(kind: string, refs: readonly string[]): Promise<SearchResult[]>;
}

/** The placeholder for the kind in the endpoint's URL, which the builder page gives. */
export const KIND = '__kind__';

type Fetch = (input: string, init?: RequestInit) => Promise<Response>;

const defaultFetch: Fetch = (input, init) => fetch(input, init);

/** The search endpoint at the URL, whose kind is KIND. */
export function createSearchApi(endpoint: string, fetchFn: Fetch = defaultFetch): SearchApi {
    const get = async (kind: string, parameters: Record<string, string>): Promise<SearchResult[]> => {
        const url = `${endpoint.replace(KIND, encodeURIComponent(kind))}?${new URLSearchParams(parameters).toString()}`;
        const response = await fetchFn(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const body: unknown = await response.json().catch(() => undefined);
        if (response.status !== 200 || !Array.isArray(body) || !body.every(isResult)) {
            throw new ApiError(`${url} answered ${response.status}.`, response.status);
        }

        return body;
    };

    return {
        search: (kind, query) => get(kind, { q: query }),
        find: async (kind, refs) => (refs.length === 0 ? [] : get(kind, { refs: refs.join(',') })),
    };
}

function isResult(value: unknown): value is SearchResult {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const { ref, name, thumbnail } = value as Record<string, unknown>;

    return typeof ref === 'string' && typeof name === 'string' && (thumbnail === null || typeof thumbnail === 'string');
}
