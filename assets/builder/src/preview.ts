/**
 * What the preview shows the working copy in, as the top bar sets it. It's kept apart from the working copy's
 * store, because changing it is no edit: it's never saved and never undone.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { Channel, ShownToAndDate } from './config';

export type PreviewWidth = 'desktop' | 'mobile';

export interface PreviewState {
    /** The entry's channels, which the preview can show it in. */
    channels: Channel[];
    /** The channel the preview shows, or null when the entry is in none. */
    channel: string | null;
    width: PreviewWidth;
    /** Who the preview shows the page to: a customer group code, '@guest' or '@no_group'. */
    shownTo: string;
    /** When the preview shows the page: a UTC date and time, or null for now. */
    date: string | null;
    setChannel(code: string): void;
    /** Follows the entry's channels as they change, keeping the channel shown while the entry is still in it. */
    setChannels(channels: Channel[]): void;
    setWidth(width: PreviewWidth): void;
    setShownTo(shownTo: string): void;
    setDate(date: string | null): void;
}

export type PreviewStore = StoreApi<PreviewState>;

/** Starts in the entry's first channel, at desktop width, shown to guests now unless the starting values say otherwise. */
export function createPreviewStore(channels: Channel[], start: ShownToAndDate = { shownTo: '@guest', date: null }): PreviewStore {
    return createStore<PreviewState>()((set) => ({
        channels,
        channel: channels[0]?.code ?? null,
        width: 'desktop',
        shownTo: start.shownTo,
        date: start.date,

        setChannel(channel) {
            set({ channel });
        },

        setChannels(entryChannels) {
            set(({ channel }) => ({
                channels: entryChannels,
                channel: entryChannels.some(({ code }) => code === channel) ? channel : (entryChannels[0]?.code ?? null),
            }));
        },

        setWidth(width) {
            set({ width });
        },

        setShownTo(shownTo) {
            set({ shownTo });
        },

        setDate(date) {
            set({ date });
        },
    }));
}
