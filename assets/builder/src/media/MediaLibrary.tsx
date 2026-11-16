import { useEffect, useId, useRef, useState, type DragEvent } from 'react';
import { useTranslator, type Translate } from '../i18n';
import { useMediaLibrary, useMediaLibraryContext } from './context';
import { Field } from './Field';
import { FolderTree } from './FolderTree';
import styles from './MediaLibrary.module.css';
import { MediaItemDetails } from './MediaItemDetails';
import type { RefusedUpload } from './store';
import { ALL_MEDIA, MEDIA_KINDS, UNSORTED, type MediaItem, type MediaKind } from './types';
import { formatSize, typeNames } from './upload';

/** How long after the last keystroke the search is sent, in milliseconds. */
const SEARCH_DELAY = 300;

/**
 * The media library: the folder tree, the search and filters, the grid of media items with their missing-alt flags, which
 * files are dropped on to upload them into the open folder, and the selected media item. Its screen in the admin shows
 * it, and the builder's media picker opens the same library. See "Media library" in docs/spec/admin.md.
 */
export function MediaLibrary() {
    const t = useTranslator();
    const { store } = useMediaLibraryContext();
    const items = useMediaLibrary((state) => state.items);
    const selected = useMediaLibrary((state) => state.selected);
    const selectedItem = items.find(({ uuid }) => uuid === selected) ?? null;

    useEffect(() => {
        void store.getState().load();
    }, [store]);

    return (
        <div className={styles.library}>
            <FolderTree />
            <section className={styles.main} aria-label={t('setono_sylius_cms.builder.media.all_media')}>
                <Toolbar />
                <UploadMessages />
                <Grid />
            </section>
            {selectedItem !== null && <MediaItemDetails key={selectedItem.uuid} item={selectedItem} />}
        </div>
    );
}

function Toolbar() {
    const t = useTranslator();
    const { store } = useMediaLibraryContext();
    const filters = useMediaLibrary((state) => state.filters);
    const uploading = useMediaLibrary((state) => state.uploading);
    // A picker offers only the kinds its field takes, and with one kind, the filter stays at it
    const kinds = filters.kinds ?? MEDIA_KINDS;
    const [search, setSearch] = useState(filters.search);
    const fileInput = useRef<HTMLInputElement>(null);
    const missingAltId = useId();

    useEffect(() => {
        if (search === store.getState().filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => void store.getState().setFilters({ search }), SEARCH_DELAY);

        return () => clearTimeout(timer);
    }, [search, store]);

    const kindLabels: Record<MediaKind, string> = {
        image: t('setono_sylius_cms.builder.media.kind_image'),
        video: t('setono_sylius_cms.builder.media.kind_video'),
        document: t('setono_sylius_cms.builder.media.kind_document'),
    };

    return (
        <div className={styles.toolbar}>
            <Field label={t('setono_sylius_cms.builder.media.search')} className={styles.searchField}>
                {(id) => (
                    <input
                        id={id}
                        type="search"
                        className={styles.input}
                        value={search}
                        placeholder={t('setono_sylius_cms.builder.media.search_placeholder')}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                )}
            </Field>
            <Field label={t('setono_sylius_cms.builder.media.kind')}>
                {(id) => (
                    <select
                        id={id}
                        className={styles.select}
                        value={filters.kind ?? ''}
                        disabled={kinds.length === 1}
                        onChange={(event) => void store.getState().setFilters({ kind: event.target.value === '' ? null : (event.target.value as MediaKind) })}
                    >
                        {kinds.length > 1 && <option value="">{t('setono_sylius_cms.builder.media.any_kind')}</option>}
                        {kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {kindLabels[kind]}
                            </option>
                        ))}
                    </select>
                )}
            </Field>
            <div className={styles.checkboxField}>
                <input
                    id={missingAltId}
                    type="checkbox"
                    checked={filters.missingAlt}
                    onChange={(event) => void store.getState().setFilters({ missingAlt: event.target.checked })}
                />
                <label htmlFor={missingAltId}>{t('setono_sylius_cms.builder.media.missing_alt')}</label>
            </div>
            <button type="button" className={styles.primary} disabled={uploading} onClick={() => fileInput.current?.click()}>
                {uploading ? t('setono_sylius_cms.builder.media.uploading') : t('setono_sylius_cms.builder.media.upload')}
            </button>
            <input
                ref={fileInput}
                type="file"
                multiple
                hidden
                aria-label={t('setono_sylius_cms.builder.media.upload')}
                onChange={(event) => {
                    const files = Array.from(event.target.files ?? []);
                    event.target.value = '';
                    void store.getState().upload(files);
                }}
            />
        </div>
    );
}

function UploadMessages() {
    const t = useTranslator();
    const { store, config } = useMediaLibraryContext();
    const refused = useMediaLibrary((state) => state.refused);
    const duplicates = useMediaLibrary((state) => state.duplicates);
    const error = useMediaLibrary((state) => state.error);
    const uploading = useMediaLibrary((state) => state.uploading);

    if (refused.length === 0 && duplicates.length === 0 && (error === null || uploading)) {
        return null;
    }

    return (
        <div className={styles.messages}>
            {refused.length > 0 && (
                <ul className={styles.refused} role="alert">
                    {refused.map((refusal, index) => (
                        <li key={`${refusal.filename}-${String(index)}`}>{refusedMessage(t, refusal, config.media)}</li>
                    ))}
                </ul>
            )}
            {duplicates.map((duplicate, index) => (
                <div key={`${duplicate.mediaItem.uuid}-${String(index)}`} className={styles.duplicate} role="status">
                    <span>{t('setono_sylius_cms.builder.media.duplicate', { '%filename%': duplicate.file.name, '%name%': duplicate.mediaItem.name })}</span>
                    <button type="button" className={styles.secondary} onClick={() => store.getState().useExisting(duplicate)}>
                        {t('setono_sylius_cms.builder.media.use_existing')}
                    </button>
                    <button type="button" className={styles.secondary} onClick={() => void store.getState().uploadCopy(duplicate)}>
                        {t('setono_sylius_cms.builder.media.upload_copy')}
                    </button>
                </div>
            ))}
            {error !== null && refused.length === 0 && duplicates.length === 0 && (
                <p className={styles.refused} role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

function refusedMessage(t: Translate, refusal: RefusedUpload, limits: Parameters<typeof typeNames>[0]): string {
    if (refusal.client === undefined) {
        return refusal.error;
    }

    const limit = formatSize(limits.maxUploadSize);

    return refusal.client.reason === 'too_big'
        ? t('setono_sylius_cms.builder.media.too_big', { '%filename%': refusal.filename, '%size%': formatSize(refusal.client.size), '%limit%': limit })
        : t('setono_sylius_cms.builder.media.not_allowed', { '%filename%': refusal.filename, '%types%': typeNames(limits).join(', '), '%limit%': limit });
}

function Grid() {
    const t = useTranslator();
    const { store } = useMediaLibraryContext();
    const items = useMediaLibrary((state) => state.items);
    const selected = useMediaLibrary((state) => state.selected);
    const loading = useMediaLibrary((state) => state.loading);
    const loadFailed = useMediaLibrary((state) => state.loadFailed);
    const page = useMediaLibrary((state) => state.page);
    const pages = useMediaLibrary((state) => state.pages);
    const filters = useMediaLibrary((state) => state.filters);
    const folders = useMediaLibrary((state) => state.folders);
    const pick = useMediaLibrary((state) => state.pick);
    const picked = useMediaLibrary((state) => state.picked);
    const [dragging, setDragging] = useState(false);

    // In a picker, a media item is pressed while it's picked, and on its own screen while it's selected
    const pressed = (item: MediaItem): boolean => (pick === null ? item.uuid === selected : picked.some(({ uuid }) => uuid === item.uuid));
    const press = (item: MediaItem): void => {
        if (pick !== null) {
            store.getState().togglePicked(item);

            return;
        }

        store.getState().select(item.uuid === selected ? null : item.uuid);
    };

    // A kind the picker stays at isn't a filter the editor set
    const kindFiltered = filters.kind !== null && (filters.kinds ?? MEDIA_KINDS).length > 1;
    const filtered = filters.search.trim() !== '' || kindFiltered || filters.missingAlt;
    const folderName =
        filters.folder === ALL_MEDIA || filters.folder === UNSORTED
            ? t('setono_sylius_cms.builder.media.unsorted')
            : (folders.find(({ uuid }) => uuid === filters.folder)?.name ?? '');

    const onDragOver = (event: DragEvent): void => {
        if (event.dataTransfer.types.includes('Files')) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            setDragging(true);
        }
    };

    const onDrop = (event: DragEvent): void => {
        event.preventDefault();
        setDragging(false);
        const files = Array.from(event.dataTransfer.files);
        if (files.length > 0) {
            void store.getState().upload(files);
        }
    };

    return (
        <div
            className={styles.dropZone}
            data-dragging={dragging || undefined}
            data-testid="media-drop-zone"
            onDragOver={onDragOver}
            onDragLeave={() => setDragging(false)}
            onDrop={onDrop}
        >
            {loadFailed && (
                <p className={styles.refused} role="alert">
                    {t('setono_sylius_cms.builder.media.load_failed')}
                </p>
            )}
            {items.length === 0 && !loading && !loadFailed && (
                <p className={styles.empty}>{filtered ? t('setono_sylius_cms.builder.media.no_matches') : t('setono_sylius_cms.builder.media.no_media')}</p>
            )}
            <ul className={styles.grid} aria-busy={loading}>
                {items.map((item) => (
                    <li key={item.uuid}>
                        <button
                            type="button"
                            className={styles.item}
                            aria-pressed={pressed(item)}
                            data-media-item={item.uuid}
                            onClick={() => press(item)}
                        >
                            <Thumbnail item={item} />
                            <span className={styles.itemName}>{item.name}</span>
                            {item.missingAlt && <span className={styles.flag}>{t('setono_sylius_cms.builder.media.missing_alt_flag')}</span>}
                        </button>
                    </li>
                ))}
            </ul>
            {page < pages && (
                <button type="button" className={styles.secondary} disabled={loading} onClick={() => void store.getState().loadMore()}>
                    {t('setono_sylius_cms.builder.media.load_more')}
                </button>
            )}
            <p className={styles.dropHint}>{t('setono_sylius_cms.builder.media.drop_here', { '%folder%': folderName })}</p>
        </div>
    );
}

function Thumbnail({ item }: { item: MediaItem }) {
    if (item.kind === 'image') {
        return <img className={styles.thumbnail} src={item.url} alt="" loading="lazy" />;
    }

    if (item.kind === 'video') {
        return <video className={styles.thumbnail} src={item.url} muted preload="metadata" />;
    }

    // A document shows its type, such as PDF
    return <span className={styles.documentThumbnail}>{(item.url.split('.').pop() ?? '').toUpperCase()}</span>;
}
