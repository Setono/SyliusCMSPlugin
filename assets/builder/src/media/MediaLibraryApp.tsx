import { useMemo } from 'react';
import { createTranslator, TranslatorProvider } from '../i18n';
import { createMediaApi, type MediaApi } from './api';
import type { MediaLibraryConfig } from './config';
import { MediaLibraryProvider } from './context';
import { MediaLibrary } from './MediaLibrary';
import { createMediaLibraryStore } from './store';

export interface MediaLibraryAppProps {
    config: MediaLibraryConfig;
    /** The endpoints, made from the config's when left out. */
    api?: MediaApi;
}

/** The media library on its own screen in the admin, with its UI strings from the screen's config. */
export function MediaLibraryApp({ config, api }: MediaLibraryAppProps) {
    const translate = useMemo(() => createTranslator(config.strings), [config]);
    const value = useMemo(() => ({ store: createMediaLibraryStore(api ?? createMediaApi(config), config.media), config }), [api, config]);

    return (
        <TranslatorProvider value={translate}>
            <MediaLibraryProvider value={value}>
                <MediaLibrary />
            </MediaLibraryProvider>
        </TranslatorProvider>
    );
}
