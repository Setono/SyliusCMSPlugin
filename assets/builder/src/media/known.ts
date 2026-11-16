/**
 * The media items the builder's media fields hold, looked up by UUID so a field shows each one's name and thumbnail.
 * Each UUID is looked up once, together with the others asked for at the same time, and a media item picked in the
 * picker is known from then on.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { MediaApi } from './api';
import type { MediaItem } from './types';

export interface KnownMediaItemsState {
    /** By UUID: the media item, or null when there's none, such as one that was deleted. A UUID not looked up yet is left out. */
    items: Readonly<Record<string, MediaItem | null>>;
    /** Looks up the media items it doesn't know yet, and isn't looking up already. */
    request(uuids: readonly string[]): void;
    /** Knows these media items from now on, such as those just picked. */
    remember(items: readonly MediaItem[]): void;
}

export type KnownMediaItemsStore = StoreApi<KnownMediaItemsState>;

export function createKnownMediaItems(api: Pick<MediaApi, 'find'>): KnownMediaItemsStore {
    const pending = new Set<string>();

    return createStore<KnownMediaItemsState>()((set, get) => ({
        items: {},

        request(uuids) {
            const unknown = [...new Set(uuids)].filter((uuid) => !(uuid in get().items) && !pending.has(uuid));
            if (unknown.length === 0) {
                return;
            }

            unknown.forEach((uuid) => pending.add(uuid));
            api.find(unknown).then(
                (found) => {
                    unknown.forEach((uuid) => pending.delete(uuid));
                    const byUuid = Object.fromEntries(found.map((item) => [item.uuid, item]));
                    set((state) => ({ items: { ...state.items, ...Object.fromEntries(unknown.map((uuid) => [uuid, byUuid[uuid.toLowerCase()] ?? null])) } }));
                },
                () => {
                    // Asked for again by the next field that shows it
                    unknown.forEach((uuid) => pending.delete(uuid));
                },
            );
        },

        remember(items) {
            set((state) => ({ items: { ...state.items, ...Object.fromEntries(items.map((item) => [item.uuid, item])) } }));
        },
    }));
}
