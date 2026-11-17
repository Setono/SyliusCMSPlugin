import * as Dialog from '@radix-ui/react-dialog';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useStore } from 'zustand';
import { useTranslator } from '../i18n';
import type { MediaApi } from './api';
import type { MediaLibraryConfig } from './config';
import { MediaLibraryProvider, useMediaLibrary } from './context';
import { createKnownMediaItems, type KnownMediaItemsStore } from './known';
import { MediaLibrary } from './MediaLibrary';
import styles from './MediaPicker.module.css';
import { createMediaLibraryStore, type PickMode } from './store';
import type { MediaItem, MediaKind } from './types';

export interface MediaPickOptions {
    /** The kinds of media item it offers. */
    kinds: readonly MediaKind[];
    /** Whether several media items are picked, as for a media list. */
    multiple: boolean;
}

/** The builder's media picker: the media library opened as a picker, and the media items its fields hold. */
export interface MediaPicker {
    /** Opens the media library as a picker, and resolves to the media items picked, in the order they were picked, or null when it's cancelled. */
    pickMedia(options: MediaPickOptions): Promise<MediaItem[] | null>;
    /** The media items the fields hold, looked up by UUID. */
    known: KnownMediaItemsStore;
}

const MediaPickerContext = createContext<MediaPicker | null>(null);

/** The media picker, or null where the builder has no media library, such as with no media endpoints in its config. */
export function useMediaPicker(): MediaPicker | null {
    return useContext(MediaPickerContext);
}

/**
 * The media items with these UUIDs, by UUID, which it looks up once: the media item, null for none, such as one that was
 * deleted, or undefined while it's looked up. Without a media picker, nothing is known.
 */
export function useKnownMediaItems(uuids: readonly string[]): Readonly<Record<string, MediaItem | null>> {
    const picker = useMediaPicker();
    const known = picker?.known ?? noneKnown;
    const items = useStore(known, (state) => state.items);
    const key = uuids.join(',');

    useEffect(() => {
        if (key !== '') {
            known.getState().request(key.split(','));
        }
    }, [known, key]);

    return items;
}

const noneKnown = createKnownMediaItems({ find: async () => [] });

interface Request extends MediaPickOptions {
    resolve: (picked: MediaItem[] | null) => void;
}

export interface MediaPickerProviderProps {
    api: MediaApi;
    config: Pick<MediaLibraryConfig, 'locales' | 'media'>;
    children: ReactNode;
}

/** Gives its children the media picker, and opens the media library in a dialog when one of them picks media. */
export function MediaPickerProvider({ api, config, children }: MediaPickerProviderProps) {
    const [request, setRequest] = useState<Request | null>(null);
    const known = useMemo(() => createKnownMediaItems(api), [api]);

    const pickMedia = useCallback(
        (options: MediaPickOptions) =>
            new Promise<MediaItem[] | null>((resolve) => {
                setRequest({ ...options, resolve });
            }),
        [],
    );
    const picker = useMemo<MediaPicker>(() => ({ pickMedia, known }), [pickMedia, known]);

    const close = (picked: MediaItem[] | null): void => {
        if (picked !== null) {
            known.getState().remember(picked);
        }

        request?.resolve(picked);
        setRequest(null);
    };

    return (
        <MediaPickerContext.Provider value={picker}>
            {children}
            {request !== null && <PickerDialog api={api} config={config} mode={{ kinds: request.kinds, multiple: request.multiple }} onClose={close} />}
        </MediaPickerContext.Provider>
    );
}

interface PickerDialogProps {
    api: MediaApi;
    config: Pick<MediaLibraryConfig, 'locales' | 'media'>;
    mode: PickMode;
    onClose: (picked: MediaItem[] | null) => void;
}

function PickerDialog({ api, config, mode, onClose }: PickerDialogProps) {
    const t = useTranslator();
    // A new library for each time it's opened, which starts at All media with the kinds the field takes
    const [library] = useState(() => ({ store: createMediaLibraryStore(api, config.media, mode), config }));

    return (
        <Dialog.Root open onOpenChange={(open) => open || onClose(null)}>
            <Dialog.Portal>
                <Dialog.Overlay className={styles.overlay} />
                <Dialog.Content className={styles.dialog} aria-describedby={undefined}>
                    <Dialog.Title className={styles.title}>{t('setono_sylius_cms.builder.media.picker_title')}</Dialog.Title>
                    {/* A picker of its own, so picking a video's poster in it opens another over it and leaves this pick open */}
                    <MediaPickerProvider api={api} config={config}>
                        <MediaLibraryProvider value={library}>
                            <div className={styles.library}>
                                <MediaLibrary />
                            </div>
                            <PickerActions onClose={onClose} />
                        </MediaLibraryProvider>
                    </MediaPickerProvider>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function PickerActions({ onClose }: Pick<PickerDialogProps, 'onClose'>) {
    const t = useTranslator();
    const picked = useMediaLibrary((state) => state.picked);
    const multiple = useMediaLibrary((state) => state.pick?.multiple ?? false);

    return (
        <div className={styles.actions}>
            <button type="button" className={styles.secondary} onClick={() => onClose(null)}>
                {t('setono_sylius_cms.builder.media.cancel')}
            </button>
            <button type="button" className={styles.primary} disabled={picked.length === 0} onClick={() => onClose(picked)}>
                {multiple && picked.length > 0 ? t('setono_sylius_cms.builder.media.choose_count', { '%count%': String(picked.length) }) : t('setono_sylius_cms.builder.media.choose')}
            </button>
        </div>
    );
}
