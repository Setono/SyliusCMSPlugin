import { createContext, useContext } from 'react';
import { useStore } from 'zustand';
import type { BuilderState, BuilderStore } from './store';

/** One open entry in the builder. */
export interface BuilderSession {
    store: BuilderStore;
    publish: () => Promise<void>;
    backUrl: string;
}

const BuilderContext = createContext<BuilderSession | null>(null);

export const BuilderProvider = BuilderContext.Provider;

export function useBuilder(): BuilderSession {
    const session = useContext(BuilderContext);
    if (session === null) {
        throw new Error('useBuilder() needs a <BuilderProvider>.');
    }

    return session;
}

/** Selects from the builder's store. The selector must return state as it is, not a new object. */
export function useBuilderState<T>(selector: (state: BuilderState) => T): T {
    return useStore(useBuilder().store, selector);
}
