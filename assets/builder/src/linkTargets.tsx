import { createContext, useContext } from 'react';
import type { LinkTargetDefinition } from './links';

const LinkTargetsContext = createContext<readonly LinkTargetDefinition[]>([]);

/** Gives Link fields' controls the link targets the registry has. */
export const LinkTargetsProvider = LinkTargetsContext.Provider;

/** The link targets, in the order the registry has them: the shipped ones first. None outside a LinkTargetsProvider. */
export function useLinkTargets(): readonly LinkTargetDefinition[] {
    return useContext(LinkTargetsContext);
}
