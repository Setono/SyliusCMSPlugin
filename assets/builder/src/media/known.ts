/**
 * The media items the builder's media fields hold, looked up by UUID so a field shows each one's name and thumbnail.
 * Each UUID is looked up once, together with the others asked for at the same time, and a media item picked in the
 * picker is known from then on.
 */
import { createLookup, type LookupStore } from '../lookup';
import type { MediaApi } from './api';
import type { MediaItem } from './types';

/** By UUID: the media item, or null when there's none, such as one that was deleted. */
export type KnownMediaItemsStore = LookupStore<MediaItem>;

export function createKnownMediaItems(api: Pick<MediaApi, 'find'>): KnownMediaItemsStore {
    return createLookup<MediaItem>(async (uuids) => {
        const found = await api.find(uuids);

        // The server answers with lowercase UUIDs
        return Object.fromEntries(uuids.flatMap((uuid) => found.filter((item) => item.uuid === uuid.toLowerCase()).map((item) => [uuid, item])));
    });
}

/** The media items by their UUIDs, as a lookup remembers them. */
export function byUuid(items: readonly MediaItem[]): Record<string, MediaItem> {
    return Object.fromEntries(items.map((item) => [item.uuid, item]));
}
