/**
 * What the preview shows the working copy in, as the top bar sets it. It's kept apart from the working copy's
 * store, because changing it is no edit: it's never saved and never undone.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { Channel } from './config';

export type PreviewWidth = 'desktop' | 'mobile';

export interface PreviewState {
    /** The entry's channels, which the preview can show it in. */
    channels: Channel[];
    /** The channel the preview shows, or null when the entry is in none. */
    channel: string | null;
    width: PreviewWidth;
    setChannel(code: string): void;
    setWidth(width: PreviewWidth): void;
}

export type PreviewStore = StoreApi<PreviewState>;

/** Starts in the entry's first channel, at desktop width. */
export function createPreviewStore(channels: Channel[]): PreviewStore {
    return createStore<PreviewState>()((set) => ({
        channels,
        channel: channels[0]?.code ?? null,
        width: 'desktop',

        setChannel(channel) {
            set({ channel });
        },

        setWidth(width) {
            set({ width });
        },
    }));
}
