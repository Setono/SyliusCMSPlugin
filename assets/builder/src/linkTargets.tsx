import { createContext, useContext } from 'react';
import type { LinkTargetDefinition } from './links';
import type { SearchResult } from './search/api';
import { useKnownRef } from './search/context';

const LinkTargetsContext = createContext<readonly LinkTargetDefinition[]>([]);

/** Gives Link fields' controls the link targets the registry has. */
export const LinkTargetsProvider = LinkTargetsContext.Provider;

/** The link targets, in the order the registry has them: the shipped ones first. None outside a LinkTargetsProvider. */
export function useLinkTargets(): readonly LinkTargetDefinition[] {
    return useContext(LinkTargetsContext);
}

/** A link's ref as the builder shows it. */
export interface LinkRef {
    /** Whether the ref is typed, as a URL is, rather than searched with its target's search, such as a product's code. */
    refIsTyped: boolean;
    /** What a searched ref refers to, as the search names it: undefined while it's looked up, for a typed or empty ref, and for one the search doesn't find. */
    found: SearchResult | undefined;
}

/** How the builder shows a link's ref: typed, or searched and named by what the search finds of it, which it looks up once. */
export function useLinkRef(link: { target: string; ref: string }): LinkRef {
    const refIsTyped = useLinkTargets().find(({ code }) => code === link.target)?.translatableRef ?? false;
    const found = useKnownRef(link.target, refIsTyped || link.ref === '' ? null : link.ref);

    return { refIsTyped, found };
}
