import { createContext, useContext } from 'react';
import { useStore } from 'zustand';
import type { BuilderApi, PreviewRequest } from './api';
import type { Channel, CustomerGroup } from './config';
import type { PreviewState, PreviewStore } from './preview';
import type { SettingsState, SettingsStore } from './settings';
import type { BuilderState, BuilderStore } from './store';

/** One open entry in the builder. */
export interface BuilderSession {
    store: BuilderStore;
    publish: () => Promise<void>;
    discard: () => Promise<void>;
    backUrl: string;
    preview: PreviewStore;
    /** Asks for a preview token, and answers with the URL of the preview it turns on. */
    createPreviewUrl: (preview: PreviewRequest) => Promise<string>;
    /** Renders blocks of the working copy under a preview token, and answers their HTML by key. */
    renderPreview: BuilderApi['renderPreview'];
    /** The entry's on-save settings. */
    settings: SettingsStore;
    /** The store's channels, which the entry's channels are picked from. */
    channels: Channel[];
    /** The store's customer groups, which the audience names. */
    customerGroups: CustomerGroup[];
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

/** Selects from the on-save settings. The selector must return state as it is, not a new object. */
export function useSettingsState<T>(selector: (state: SettingsState) => T): T {
    return useStore(useBuilder().settings, selector);
}

/** Selects from what the preview shows. The selector must return state as it is, not a new object. */
export function usePreviewState<T>(selector: (state: PreviewState) => T): T {
    return useStore(useBuilder().preview, selector);
}
