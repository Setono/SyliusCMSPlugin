/** What a media item is. See "What the library holds" in docs/spec/media.md. */
export type MediaKind = 'image' | 'video' | 'document';

export const MEDIA_KINDS: readonly MediaKind[] = ['image', 'video', 'document'];

/** A point from 0 to 1 on each axis, from the top left. */
export interface FocalPoint {
    x: number;
    y: number;
}

/** A media item as the media endpoints answer with it. */
export interface MediaItem {
    uuid: string;
    kind: MediaKind;
    name: string;
    originalFilename: string;
    mimeType: string;
    /** In bytes. */
    size: number;
    width: number | null;
    height: number | null;
    credit: string | null;
    /** By locale, without empty ones. */
    altTexts: Record<string, string>;
    /** An image without alt text in one or more of the store's locales. */
    missingAlt: boolean;
    /** Images only. */
    focalPoint: FocalPoint | null;
    /** Videos only: an image media item's UUID. */
    poster: string | null;
    /** null for Unsorted. */
    folder: string | null;
    /** The current file's public URL. */
    url: string;
    /** How many entries use it, as the usage index has it. It can't be deleted while any do. */
    usageCount: number;
    createdAt: string;
    changedAt: string;
}

export interface MediaFolder {
    uuid: string;
    name: string;
    /** null at the top of the library. */
    parent: string | null;
}

export interface MediaPage {
    items: MediaItem[];
    page: number;
    pages: number;
    total: number;
}

/** A file whose content matches an existing media item's current file: the editor is offered that media item instead. */
export interface Duplicate {
    filename: string;
    mediaItem: MediaItem;
}

/** A file the library won't take, with the message naming the limit. */
export interface Refused {
    filename: string;
    error: string;
}

export interface UploadAnswer {
    created: MediaItem[];
    duplicates: Duplicate[];
    refused: Refused[];
}

/** All media, Unsorted, or a folder by its UUID. */
export const ALL_MEDIA = 'all';
export const UNSORTED = 'unsorted';

/** What the library shows. See "Search and filters" in docs/spec/media.md. */
export interface MediaFilters {
    /** ALL_MEDIA, UNSORTED or a folder's UUID. */
    folder: string;
    search: string;
    /** One kind, or null for any of those it may show. */
    kind: MediaKind | null;
    missingAlt: boolean;
    /** Only the media items nothing uses. */
    unused?: boolean;
    /** The only kinds it may show, such as those a media field takes in the builder's media picker. Every kind without it. */
    kinds?: readonly MediaKind[];
}

/** An entry that uses a media item, as its Used in list shows it. See "Usage index" in docs/spec/media.md. */
export interface MediaUse {
    /** The entry's UUID. */
    entry: string;
    /** Its title as the admin's lists show it, or null when it has none. */
    title: string | null;
    /** Its content type, as editors call it. */
    contentType: string;
    /** Its overview page in the admin. */
    url: string;
    /** Whether its draft uses the media item. */
    draft: boolean;
    /** Whether its published content, which shoppers see, uses the media item. */
    published: boolean;
}

/** What a save changes. Left out stays as it is. */
export interface MediaItemChanges {
    name?: string;
    /** A folder's UUID, or null for Unsorted. */
    folder?: string | null;
    credit?: string | null;
    /** The locales that change. An empty one removes the alt text. */
    altTexts?: Record<string, string>;
    focalPoint?: FocalPoint;
    /** A video's poster: an image media item's UUID, or null for none. */
    poster?: string | null;
}
