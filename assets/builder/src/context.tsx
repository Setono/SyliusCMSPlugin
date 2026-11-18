import { createContext, useCallback, useContext, useMemo, useSyncExternalStore } from 'react';
import { useStore } from 'zustand';
import type { BuilderApi, PreviewRequest } from './api';
import type { Channel, CustomerGroup, Locale, SnippetPlacement } from './config';
import { providedContext } from './hostPageContext';
import type { LeavingState, LeavingStore } from './leaving';
import { localeOf } from './locales';
import type { PresenceState, PresenceStore } from './presence';
import type { PreviewState, PreviewStore } from './preview';
import type { SettingsState, SettingsStore } from './settings';
import type { SnippetPreviewApi } from './snippetPreview';
import type { InsertedPreset, PresetListing, StoredBlock } from './types';
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
    /** Schedules a publish at a time in UTC, and resolves to whether it was scheduled. */
    schedule: (at: string) => Promise<boolean>;
    /** Cancels the scheduled publish, which unlocks the working copy. */
    cancelSchedule: () => Promise<void>;
    /** Loads the working copy again, when the server can't merge a save onto what colleagues saved. */
    reload: () => Promise<void>;
    /** Where the back button and Esc go: where the builder was opened from. */
    backUrl: string;
    /** Leaves the builder for backUrl, once the unsaved changes are sent, or stays when they can't be. */
    leave: () => void;
    /** Whether leaving stayed because the unsaved changes couldn't be sent, and the way to stay or leave anyway. */
    leaving: LeavingStore;
    /** The entry's overview page, for publishing history, revisions, share links and placements. */
    overviewUrl: string;
    /** A snippet's placements, which are managed on its overview page, or null for an entry that isn't a snippet. */
    placements: SnippetPlacement[] | null;
    /** What the top bar shows of a snippet's preview and the sample objects it can show it with. Null without the endpoints. */
    snippetPreview: SnippetPreviewApi | null;
    /**
     * Opens a snippet in the builder, once the unsaved changes are sent, with a way back to the Snippet block that shows
     * it. Null when the builder page doesn't say where another entry's builder is.
     */
    editSnippet: ((snippet: string, block: string) => void) | null;
    /** The blocks a snippet shows, which Detach to edit here copies. Null without the endpoint. */
    snippetBlocks: ((snippet: string) => Promise<StoredBlock[]>) | null;
    /** The block picker's Presets tab: the presets at a spot, once the unsaved changes are sent, and a preset's blocks ready to insert. Null without the endpoints. */
    presets: PresetsSession | null;
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

/** What the Presets tab asks the server. */
export interface PresetsSession {
    /** The block presets at the top level, or in a block's children, as the server sees the working copy once the unsaved changes are sent. */
    list(parent: string | null): Promise<PresetListing[]>;
    /** A preset's blocks ready to insert into the entry. */
    insert(id: string): Promise<InsertedPreset>;
}

const BuilderContext = createContext<BuilderSession | null>(null);

export const BuilderProvider = BuilderContext.Provider;

/** The open entry's session, or null outside the builder, such as a field control rendered on its own. */
export function useBuilderSession(): BuilderSession | null {
    return useContext(BuilderContext);
}

export function useBuilder(): BuilderSession {
    const session = useContext(BuilderContext);
    if (session === null) {
        throw new Error('useBuilder() needs a <BuilderProvider>.');
    }

    return session;
}

/**
 * The locale first, then the locales its translatable values fall back through, as the store has them, or only the
 * locale outside the builder
 */
export function useFallbackChain(locale: string): readonly string[] {
    const session = useContext(BuilderContext);

    return session === null ? [locale] : localeOf(session.locales, locale).fallbackChain;
}

/** Selects from the builder's store. The selector must return state as it is, not a new object. */
export function useBuilderState<T>(selector: (state: BuilderState) => T): T {
    return useStore(useBuilder().store, selector);
}

/**
 * The context keys the edited entry provides its tree, or the snippet declares, which follows the snippet's context
 * setting as the editor changes it. Null outside the builder, such as a field control rendered on its own, where
 * nothing narrows what's offered.
 */
export function useProvidedContext(): readonly string[] | null {
    const store = useContext(BuilderContext)?.store ?? null;
    const subscribe = useCallback((changed: () => void) => store?.subscribe(changed) ?? (() => undefined), [store]);
    // The keys joined, a string, so only a change of them renders again
    const joined = useSyncExternalStore(subscribe, () => {
        if (store === null) {
            return null;
        }

        const { rules, entry } = store.getState();

        return providedContext(rules.provides, entry).join(',');
    });

    return useMemo(() => (joined === null ? null : joined === '' ? [] : joined.split(',')), [joined]);
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

/** Selects from leaving the builder. The selector must return state as it is, not a new object. */
export function useLeavingState<T>(selector: (state: LeavingState) => T): T {
    return useStore(useBuilder().leaving, selector);
}
