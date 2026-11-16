/**
 * What the URL & SEO tab shows beside the slug, as the server has it: warnings about the entry's URLs, and the URLs of
 * its old slugs, which redirect. See "Right pane: the selection" in docs/spec/builder.md.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { BuilderStore } from './store';
import type { EntryUrlsResponse, ValidationError } from './types';

export interface UrlsState {
    /** URLs other entries have drafted, and published URLs another route takes now. They point at the slug in a locale. */
    warnings: ValidationError[];
    /** The paths of the URLs of the old slugs that redirect, by locale, newest first. */
    oldUrls: Record<string, string[]>;
    setUrls(urls: EntryUrlsResponse): void;
}

export type UrlsStore = StoreApi<UrlsState>;

export function createUrlsStore(): UrlsStore {
    return createStore<UrlsState>()((set) => ({
        warnings: [],
        oldUrls: {},
        setUrls({ warnings, oldUrls }) {
            set({ warnings, oldUrls });
        },
    }));
}

export interface UrlsSync {
    /** Asks the server for the warnings and the old URLs, and keeps them. */
    refresh(): Promise<void>;
    dispose(): void;
}

/**
 * Keeps the warnings and the old URLs in step with the server: they're read again when a save has landed, when a
 * working copy is loaded in place of the one being edited, and when the entry's state changes, such as on a publish,
 * which records old slugs. An answer to an older request than the last is ignored.
 */
export function createUrlsSync(store: BuilderStore, urls: UrlsStore, fetchUrls: () => Promise<EntryUrlsResponse>): UrlsSync {
    let requests = 0;

    const refresh = async (): Promise<void> => {
        const request = ++requests;

        try {
            const answer = await fetchUrls();
            if (request === requests) {
                urls.getState().setUrls(answer);
            }
        } catch {
            // What's shown stays as it was
        }
    };

    const unsubscribe = store.subscribe((state, previous) => {
        const saved = state.saveState === 'saved' && previous.saveState !== 'saved';
        const loaded = state.entry !== previous.entry && state.changeCount === previous.changeCount;
        if (saved || loaded || state.status !== previous.status) {
            void refresh();
        }
    });

    return { refresh, dispose: unsubscribe };
}

/** The messages of the slug in a locale. */
export function slugMessages(messages: readonly ValidationError[], locale: string): string[] {
    return messages.filter((message) => message.block === null && message.field === 'slug' && message.locale === locale).map(({ message }) => message);
}
