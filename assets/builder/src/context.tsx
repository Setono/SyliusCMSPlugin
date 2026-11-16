import { createContext, useContext } from 'react';
import { useStore } from 'zustand';
import type { BuilderApi, PreviewRequest } from './api';
import type { Channel, CustomerGroup, Locale } from './config';
import type { PresenceState, PresenceStore } from './presence';
import type { PreviewState, PreviewStore } from './preview';
import type { SettingsState, SettingsStore } from './settings';
import type { BuilderState, BuilderStore } from './store';
import type { UrlsState, UrlsStore } from './urls';
import type { ValidationState, ValidationStore } from './validation';

/** One open entry in the builder. */
export interface BuilderSession {
    store: BuilderStore;
    /** What publish would refuse in the working copy, as the server last validated it. */
    validation: ValidationStore;
    publish: () => Promise<void>;
    discard: () => Promise<void>;
    /** Loads the working copy again, when the server can't merge a save onto what colleagues saved. */
    reload: () => Promise<void>;
    /** Where the back button and Esc go: where the builder was opened from. */
    backUrl: string;
    /** Leaves the builder for backUrl, once the unsaved changes are sent. */
    leave: () => void;
    /** The entry's overview page, for publishing history, revisions, share links and placements. */
    overviewUrl: string;
    preview: PreviewStore;
    /** Asks for a preview token, and answers with the URL of the preview it turns on. */
    createPreviewUrl: (preview: PreviewRequest) => Promise<string>;
    /** Renders blocks of the working copy under a preview token, and answers their HTML by key. */
    renderPreview: BuilderApi['renderPreview'];
    /** The entry's on-save settings. */
    settings: SettingsStore;
    /** Who else has the entry open. */
    presence: PresenceStore;
    /** The store's channels, which the entry's channels are picked from. */
    channels: Channel[];
    /** The locales of the store's channels, with their names and fallback chains. */
    locales: Locale[];
    /** The store's customer groups, which the audience names. */
    customerGroups: CustomerGroup[];
    /** The URL prefix of the entry's content type, '' for URLs at the root, or null when it has no URL of its own. */
    urlPrefix: string | null;
    /** Whether the entry can be a channel's homepage, which only a page can. */
    homepage: boolean;
    /** The warnings about the entry's URLs and the URLs of its old slugs, as the server has them. */
    urls: UrlsStore;
    /** Says the editor changed the title in a locale, so the slug can follow it. */
    titleChanged: (locale: string, before: string | null) => void;
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

/** Selects from who else has the entry open. The selector must return state as it is, not a new object. */
export function usePresenceState<T>(selector: (state: PresenceState) => T): T {
    return useStore(useBuilder().presence, selector);
}

/** Selects from the validation errors. The selector must return state as it is, or a primitive, not a new object. */
export function useValidationState<T>(selector: (state: ValidationState) => T): T {
    return useStore(useBuilder().validation, selector);
}

/** Selects from the URL warnings and old URLs. The selector must return state as it is, not a new object. */
export function useUrlsState<T>(selector: (state: UrlsState) => T): T {
    return useStore(useBuilder().urls, selector);
}

/** Selects from what the preview shows. The selector must return state as it is, not a new object. */
export function usePreviewState<T>(selector: (state: PreviewState) => T): T {
    return useStore(useBuilder().preview, selector);
}
