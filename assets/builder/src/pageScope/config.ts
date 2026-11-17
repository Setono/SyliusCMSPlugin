/**
 * What the page scope editor of the Placements section of a snippet's overview page gets from the page. See "Placements"
 * in docs/spec/admin.md and "Page scopes" in docs/spec/slots.md
 */
import type { ConditionKindDefinition, ContextKeyDefinition, VisibilityCondition } from '../types';

/** What a section's editors share, as JSON in the data-config attribute of its [data-cms-page-scopes]. */
export interface PageScopesConfig {
    /** The condition kinds a page scope can hold: those that read the host page. */
    conditionKinds: ConditionKindDefinition[];
    contextKeys: ContextKeyDefinition[];
    endpoints: {
        /** What pickers search, with __kind__ for the kind, such as product. */
        search: string;
    };
    /** The admin user's locale. */
    locale: string;
    strings: Record<string, string>;
}

/** One placement's, as JSON in the data-config attribute of its [data-cms-page-scope]. */
export interface PlacementScopeConfig {
    /** Where the scope is posted, as `scope`, with `_csrf_token`. */
    action: string;
    csrfToken: string;
    /** The placement's slot, by its label. */
    slot: string;
    /** The page scope's conditions as they're stored, in order. */
    scope: VisibilityCondition[];
    /** The codes of the kinds the placement's slot provides the context of, which can be picked. */
    kinds: string[];
}

export function readPageScopesConfig(element: HTMLElement): PageScopesConfig {
    return read<PageScopesConfig>(element, 'The page scope editors need their configuration in the data-config attribute.');
}

export function readPlacementScopeConfig(element: HTMLElement): PlacementScopeConfig {
    return read<PlacementScopeConfig>(element, "A page scope editor needs its placement's configuration in the data-config attribute.");
}

function read<T>(element: HTMLElement, missing: string): T {
    const json = element.dataset.config;
    if (json === undefined || json === '') {
        throw new Error(missing);
    }

    return JSON.parse(json) as T;
}
