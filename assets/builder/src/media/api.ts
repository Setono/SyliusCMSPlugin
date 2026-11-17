import { ApiError } from '../api';
import type { MediaLibraryConfig } from './config';
import { ALL_MEDIA, type MediaFilters, type MediaFolder, type MediaItem, type MediaItemChanges, type MediaPage, type MediaUse, type UploadAnswer } from './types';

/** The media library's endpoints. They're internal, and change together with the app. */
export interface MediaApi {
    /** A page of what the library shows, newest first. */
    list(filters: MediaFilters, page: number): Promise<MediaPage>;
    /** The media items with these UUIDs, in no particular order. Those that don't exist, such as deleted ones, are left out. */
    find(uuids: readonly string[]): Promise<MediaItem[]>;
    /** Uploads files into a folder, or Unsorted for null. With copy, a duplicate is uploaded as a copy. */
    upload(files: readonly File[], folder: string | null, copy: boolean): Promise<UploadAnswer>;
    /** Saves a media item's metadata, which takes effect at once, and answers with it. */
    save(uuid: string, changes: MediaItemChanges): Promise<MediaItem>;
    /** The entries that use a media item, its Used in list. */
    uses(uuid: string): Promise<MediaUse[]>;
    /** Replaces a media item's file, which takes effect at once wherever it's used, and answers with it. */
    replace(uuid: string, file: File): Promise<MediaItem>;
    /** Deletes a media item that nothing uses, with its file. */
    remove(uuid: string): Promise<void>;
    folders(): Promise<MediaFolder[]>;
    /** Creates a folder inside another, or at the top for null. */
    createFolder(name: string, parent: string | null): Promise<MediaFolder>;
    /** Renames a folder, or moves it into another or to the top for a parent of null. */
    updateFolder(uuid: string, changes: { name?: string; parent?: string | null }): Promise<MediaFolder>;
}

type Fetch = (input: string, init?: RequestInit) => Promise<Response>;

const defaultFetch: Fetch = (input, init) => fetch(input, init);

export function createMediaApi(config: Pick<MediaLibraryConfig, 'csrfToken' | 'endpoints'>, fetchFn: Fetch = defaultFetch): MediaApi {
    const request = async (url: string, init: RequestInit = {}): Promise<unknown> => {
        const response = await fetchFn(url, { credentials: 'same-origin', ...init, headers: { Accept: 'application/json', ...init.headers } });
        const body: unknown = await response.json().catch(() => undefined);
        if (response.status !== 200 && response.status !== 201 && response.status !== 204) {
            throw new ApiError(errorMessage(body) ?? `${url} answered ${response.status}.`, response.status);
        }

        if (response.status === 204) {
            return undefined;
        }

        if (body === undefined) {
            throw new ApiError(`${url} didn't answer with JSON.`, response.status);
        }

        return body;
    };

    const write = (method: 'POST' | 'PATCH', body: unknown): RequestInit => ({
        method,
        headers: { 'X-CSRF-Token': config.csrfToken, 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });

    return {
        async list(filters, page) {
            return (await request(`${config.endpoints.media}?${listQuery(filters, page)}`)) as MediaPage;
        },

        async find(uuids) {
            const found: MediaItem[] = [];
            // The endpoint answers a page at a time
            for (let start = 0; start < uuids.length; start += FIND_CHUNK) {
                const query = new URLSearchParams({ uuids: uuids.slice(start, start + FIND_CHUNK).join(',') });
                found.push(...((await request(`${config.endpoints.media}?${query.toString()}`)) as MediaPage).items);
            }

            return found;
        },

        async upload(files, folder, copy) {
            const body = new FormData();
            for (const file of files) {
                body.append('files[]', file, file.name);
            }

            if (folder !== null) {
                body.append('folder', folder);
            }

            if (copy) {
                body.append('copy', '1');
            }

            // The browser sets the multipart Content-Type with its boundary
            return (await request(config.endpoints.media, { method: 'POST', headers: { 'X-CSRF-Token': config.csrfToken }, body })) as UploadAnswer;
        },

        async save(uuid, changes) {
            return (await request(`${config.endpoints.media}/${uuid}`, write('PATCH', changes))) as MediaItem;
        },

        async uses(uuid) {
            return ((await request(`${config.endpoints.media}/${uuid}/uses`)) as { uses: MediaUse[] }).uses;
        },

        async replace(uuid, file) {
            const body = new FormData();
            body.append('file', file, file.name);

            // The browser sets the multipart Content-Type with its boundary
            return (await request(`${config.endpoints.media}/${uuid}/file`, { method: 'POST', headers: { 'X-CSRF-Token': config.csrfToken }, body })) as MediaItem;
        },

        async remove(uuid) {
            await request(`${config.endpoints.media}/${uuid}`, { method: 'DELETE', headers: { 'X-CSRF-Token': config.csrfToken } });
        },

        async folders() {
            const body = await request(config.endpoints.folders);

            return (body as { folders: MediaFolder[] }).folders;
        },

        async createFolder(name, parent) {
            return (await request(config.endpoints.folders, write('POST', { name, parent }))) as MediaFolder;
        },

        async updateFolder(uuid, changes) {
            return (await request(`${config.endpoints.folders}/${uuid}`, write('PATCH', changes))) as MediaFolder;
        },
    };
}

/** How many media items the list endpoint answers with at most, which find() asks for at a time. */
const FIND_CHUNK = 60;

/**
 * The list endpoint's query: the folder, unless it's All media, the search, the kind, or the kinds it may show
 * separated by commas, the missing-alt and unused filters and the page.
 */
export function listQuery(filters: MediaFilters, page: number): string {
    const query = new URLSearchParams();
    if (filters.folder !== ALL_MEDIA) {
        query.set('folder', filters.folder);
    }

    if (filters.search.trim() !== '') {
        query.set('q', filters.search.trim());
    }

    if (filters.kind !== null) {
        query.set('kind', filters.kind);
    } else if (filters.kinds !== undefined && filters.kinds.length > 0) {
        query.set('kind', filters.kinds.join(','));
    }

    if (filters.missingAlt) {
        query.set('missingAlt', '1');
    }

    if (filters.unused === true) {
        query.set('unused', '1');
    }

    query.set('page', String(page));

    return query.toString();
}

function errorMessage(body: unknown): string | undefined {
    return typeof body === 'object' && body !== null && 'error' in body && typeof body.error === 'string' ? body.error : undefined;
}
