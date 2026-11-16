/**
 * The media library's state: the folders, what it shows and the page it's at, the selected media item, and what became of
 * the last upload. Every change is sent to the server at once, since media items take effect on save (ADR 0013). See
 * "Media library" in docs/spec/admin.md.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import type { MediaApi } from './api';
import type { MediaLimits } from './config';
import { checkFile, type ClientRefusal } from './upload';
import { ALL_MEDIA, UNSORTED, type MediaFilters, type MediaFolder, type MediaItem, type MediaItemChanges, type MediaKind, type Refused } from './types';

/** A file whose content matches an existing media item's current file, kept so a copy can still be uploaded. */
export interface PendingDuplicate {
    file: File;
    mediaItem: MediaItem;
}

/** A file refused before or after it was sent: by the browser's check, with its reason, or by the server, with its message. */
export type RefusedUpload = (Refused & { client?: undefined }) | { filename: string; client: ClientRefusal };

export type SaveState = 'idle' | 'saving' | 'saved' | 'failed';

/** The library opened as the builder's media picker: the kinds of media item it offers, and whether several are picked. */
export interface PickMode {
    kinds: readonly MediaKind[];
    multiple: boolean;
}

export interface MediaLibraryState {
    /** The picker it's opened as, or null for the library on its own screen. */
    pick: PickMode | null;
    /** What's picked, in the order it was picked: one media item at most, unless several are picked. */
    picked: MediaItem[];
    folders: MediaFolder[];
    filters: MediaFilters;
    items: MediaItem[];
    page: number;
    pages: number;
    total: number;
    loading: boolean;
    loadFailed: boolean;
    selected: string | null;
    uploading: boolean;
    refused: RefusedUpload[];
    duplicates: PendingDuplicate[];
    saveState: SaveState;
    /** Why the last save, upload or folder change failed. */
    error: string | null;

    /** Loads the folders and the first page of what's shown. */
    load(): Promise<void>;
    /** Changes what's shown, and loads its first page. */
    setFilters(filters: Partial<MediaFilters>): Promise<void>;
    loadMore(): Promise<void>;
    select(uuid: string | null): void;
    /**
     * In a picker: picks the media item, which selects it, or unpicks it when it's picked. Picking one where only one is
     * picked unpicks the other.
     */
    togglePicked(item: MediaItem): void;
    /** Uploads files into the open folder, or Unsorted when the library shows All media or Unsorted. In a picker, it picks those uploaded. */
    upload(files: readonly File[]): Promise<void>;
    /** Takes the existing media item a duplicate offers, which selects it, and in a picker picks it. */
    useExisting(duplicate: PendingDuplicate): void;
    uploadCopy(duplicate: PendingDuplicate): Promise<void>;
    dismissUploadMessages(): void;
    /** Saves a media item's metadata. A move takes it out of a folder that's shown. */
    save(uuid: string, changes: MediaItemChanges): Promise<boolean>;
    /** Creates a folder inside the open folder, or at the top, and opens it. */
    createFolder(name: string): Promise<boolean>;
    renameFolder(uuid: string, name: string): Promise<boolean>;
    moveFolder(uuid: string, parent: string | null): Promise<boolean>;
}

export type MediaLibraryStore = StoreApi<MediaLibraryState>;

export const initialFilters: MediaFilters = { folder: ALL_MEDIA, search: '', kind: null, missingAlt: false };

/**
 * @param pick the picker it's opened as in the builder, which shows only the kinds of media item it offers, with the kind
 *             filter set to the one kind when it offers one. Without it, the library on its own screen.
 */
export function createMediaLibraryStore(api: MediaApi, limits: MediaLimits, pick: PickMode | null = null): MediaLibraryStore {
    // Only the answer to the last list request is shown
    let listRequests = 0;
    const filters: MediaFilters = pick === null ? initialFilters : { ...initialFilters, kind: pick.kinds.length === 1 ? (pick.kinds[0] ?? null) : null, kinds: pick.kinds };

    return createStore<MediaLibraryState>()((set, get) => {
        /** In a picker, picks these media items of the kinds it offers: all of them, or the first where only one is picked. */
        const pickAll = (items: readonly MediaItem[]): void => {
            const offered = items.filter(({ kind }) => pick?.kinds.includes(kind) ?? false);
            const first = offered[0];
            if (pick === null || first === undefined) {
                return;
            }

            set((state) => ({
                picked: pick.multiple ? [...state.picked, ...offered.filter(({ uuid }) => !state.picked.some((picked) => picked.uuid === uuid))] : [first],
                selected: first.uuid,
                saveState: 'idle',
            }));
        };

        const loadPage = async (page: number): Promise<void> => {
            const request = ++listRequests;
            set({ loading: true, loadFailed: false });

            try {
                const answer = await api.list(get().filters, page);
                if (request !== listRequests) {
                    return;
                }

                set((state) => ({
                    items: page === 1 ? answer.items : [...state.items, ...answer.items.filter(({ uuid }) => !state.items.some((item) => item.uuid === uuid))],
                    page: answer.page,
                    pages: answer.pages,
                    total: answer.total,
                    loading: false,
                }));
            } catch {
                if (request === listRequests) {
                    set({ loading: false, loadFailed: true });
                }
            }
        };

        const failed = (error: unknown): void => {
            set({ error: error instanceof Error ? error.message : String(error) });
        };

        const send = async (files: readonly File[], copy: boolean): Promise<void> => {
            const { folder } = get().filters;
            set({ uploading: true, error: null });

            try {
                const answer = await api.upload(files, folder === ALL_MEDIA || folder === UNSORTED ? null : folder, copy);
                set((state) => ({
                    refused: [...state.refused, ...answer.refused],
                    duplicates: [
                        ...state.duplicates,
                        ...answer.duplicates.flatMap(({ filename, mediaItem }) => {
                            const file = files.find(({ name }) => name === filename);

                            return file === undefined ? [] : [{ file, mediaItem }];
                        }),
                    ],
                }));

                if (answer.created.length > 0) {
                    await loadPage(1);
                    pickAll(answer.created);
                }
            } catch (error) {
                failed(error);
            } finally {
                set({ uploading: false });
            }
        };

        const replaceFolder = (folder: MediaFolder): void => {
            set((state) => ({ folders: state.folders.map((existing) => (existing.uuid === folder.uuid ? folder : existing)) }));
        };

        return {
            pick,
            picked: [],
            folders: [],
            filters,
            items: [],
            page: 1,
            pages: 1,
            total: 0,
            loading: false,
            loadFailed: false,
            selected: null,
            uploading: false,
            refused: [],
            duplicates: [],
            saveState: 'idle',
            error: null,

            async load() {
                const [folders] = await Promise.all([
                    api.folders().catch(() => {
                        set({ loadFailed: true });

                        return [];
                    }),
                    loadPage(1),
                ]);
                set({ folders });
            },

            async setFilters(filters) {
                set((state) => ({ filters: { ...state.filters, ...filters } }));
                await loadPage(1);
            },

            async loadMore() {
                const { page, pages, loading } = get();
                if (!loading && page < pages) {
                    await loadPage(page + 1);
                }
            },

            select(uuid) {
                set({ selected: uuid, saveState: 'idle', error: null });
            },

            togglePicked(item) {
                const { picked } = get();
                if (picked.some(({ uuid }) => uuid === item.uuid)) {
                    const rest = picked.filter(({ uuid }) => uuid !== item.uuid);
                    set({ picked: rest, selected: rest.at(-1)?.uuid ?? null, saveState: 'idle', error: null });

                    return;
                }

                set({ picked: pick?.multiple === true ? [...picked, item] : [item], selected: item.uuid, saveState: 'idle', error: null });
            },

            async upload(files) {
                const refused: RefusedUpload[] = [];
                const allowed: File[] = [];
                for (const file of files) {
                    const refusal = checkFile(file, limits);
                    if (refusal === null) {
                        allowed.push(file);
                    } else {
                        refused.push({ filename: file.name, client: refusal });
                    }
                }

                set({ refused, duplicates: [] });
                if (allowed.length > 0) {
                    await send(allowed, false);
                }
            },

            useExisting(duplicate) {
                set((state) => ({
                    duplicates: state.duplicates.filter((pending) => pending !== duplicate),
                    // It's shown even when it isn't in what the library shows
                    items: state.items.some(({ uuid }) => uuid === duplicate.mediaItem.uuid) ? state.items : [duplicate.mediaItem, ...state.items],
                    selected: duplicate.mediaItem.uuid,
                }));
                pickAll([duplicate.mediaItem]);
            },

            async uploadCopy(duplicate) {
                set((state) => ({ duplicates: state.duplicates.filter((pending) => pending !== duplicate) }));
                await send([duplicate.file], true);
            },

            dismissUploadMessages() {
                set({ refused: [], duplicates: [] });
            },

            async save(uuid, changes) {
                set({ saveState: 'saving', error: null });

                try {
                    const saved = await api.save(uuid, changes);
                    const { folder } = get().filters;
                    const movedAway = changes.folder !== undefined && folder !== ALL_MEDIA && (folder === UNSORTED ? saved.folder !== null : saved.folder !== folder);
                    set((state) => ({
                        items: movedAway ? state.items.filter((item) => item.uuid !== uuid) : state.items.map((item) => (item.uuid === uuid ? saved : item)),
                        picked: state.picked.map((item) => (item.uuid === uuid ? saved : item)),
                        selected: movedAway ? null : state.selected,
                        saveState: 'saved',
                    }));

                    return true;
                } catch (error) {
                    set({ saveState: 'failed' });
                    failed(error);

                    return false;
                }
            },

            async createFolder(name) {
                const { folder } = get().filters;

                try {
                    const created = await api.createFolder(name, folder === ALL_MEDIA || folder === UNSORTED ? null : folder);
                    set((state) => ({ folders: [...state.folders, created], error: null }));
                    await get().setFilters({ folder: created.uuid });

                    return true;
                } catch (error) {
                    failed(error);

                    return false;
                }
            },

            async renameFolder(uuid, name) {
                try {
                    replaceFolder(await api.updateFolder(uuid, { name }));
                    set({ error: null });

                    return true;
                } catch (error) {
                    failed(error);

                    return false;
                }
            },

            async moveFolder(uuid, parent) {
                try {
                    replaceFolder(await api.updateFolder(uuid, { parent }));
                    set({ error: null });

                    return true;
                } catch (error) {
                    failed(error);

                    return false;
                }
            },
        };
    });
}
