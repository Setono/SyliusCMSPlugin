import { useMemo } from 'react';
import { createTranslator, TranslatorProvider } from '../i18n';
import { createMediaApi, type MediaApi } from './api';
import type { MediaLibraryConfig } from './config';
import { MediaLibraryProvider } from './context';
import { MediaLibrary } from './MediaLibrary';
import { MediaPickerProvider } from './picker';
import { createMediaLibraryStore } from './store';

export interface MediaLibraryAppProps {
    config: MediaLibraryConfig;
    /** The endpoints, made from the config's when left out. */
    api?: MediaApi;
}

/** The media library on its own screen in the admin, with its UI strings from the screen's config. */
export function MediaLibraryApp({ config, api }: MediaLibraryAppProps) {
    const translate = useMemo(() => createTranslator(config.strings), [config]);
    const mediaApi = useMemo(() => api ?? createMediaApi(config), [api, config]);
    const value = useMemo(() => ({ store: createMediaLibraryStore(mediaApi, config.media), config }), [mediaApi, config]);

    // The picker a video's poster is picked with
    return (
        <TranslatorProvider value={translate}>
            <MediaPickerProvider api={mediaApi} config={config}>
                <MediaLibraryProvider value={value}>
                    <MediaLibrary />
                </MediaLibraryProvider>
            </MediaPickerProvider>
        </TranslatorProvider>
    );
}
