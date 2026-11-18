import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { offeredProperties, type BindablePropertyDefinition } from './bindings';
import { useProvidedContext } from './context';

const NONE: readonly string[] = [];

/** The bindable properties, and the context keys the entry provides or the snippet declares, which decide what fits. */
export interface Bindings {
    properties: readonly BindablePropertyDefinition[];
    provided: readonly string[];
}

const BindingsContext = createContext<Bindings>({ properties: [], provided: [] });

/** Gives bindable fields the bindable properties and the context they fit, as given. */
export const BindingsProvider = BindingsContext.Provider;

/**
 * Gives bindable fields the registry's bindable properties, and the context the entry provides or the snippet declares,
 * which follows the snippet's context setting as the editor changes it. Inside a BuilderProvider.
 */
export function BuilderBindingsProvider({ properties, children }: { properties: readonly BindablePropertyDefinition[]; children: ReactNode }) {
    const provided = useProvidedContext() ?? NONE;
    const value = useMemo(() => ({ properties, provided }), [properties, provided]);

    return <BindingsContext.Provider value={value}>{children}</BindingsContext.Provider>;
}

/** Every bindable property, in the registry's order, to name a bound one by. None outside a provider. */
export function useBindableProperties(): readonly BindablePropertyDefinition[] {
    return useContext(BindingsContext).properties;
}

/** The properties a field of these binding types can show here: their type fits, and the context they need is provided. */
export function useOfferedProperties(accepts: readonly string[]): BindablePropertyDefinition[] {
    const { properties, provided } = useContext(BindingsContext);

    return useMemo(() => offeredProperties(properties, accepts, provided), [properties, accepts, provided]);
}
