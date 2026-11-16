import { createContext, useContext } from 'react';
import { useStore } from 'zustand';
import type { MediaLibraryConfig } from './config';
import type { MediaLibraryState, MediaLibraryStore } from './store';

export interface MediaLibraryContextValue {
    store: MediaLibraryStore;
    config: Pick<MediaLibraryConfig, 'locales' | 'media'>;
}

const MediaLibraryContext = createContext<MediaLibraryContextValue | null>(null);

export const MediaLibraryProvider = MediaLibraryContext.Provider;

export function useMediaLibraryContext(): MediaLibraryContextValue {
    const value = useContext(MediaLibraryContext);
    if (value === null) {
        throw new Error('The media library is used outside its provider.');
    }

    return value;
}

export function useMediaLibrary<T>(selector: (state: MediaLibraryState) => T): T {
    return useStore(useMediaLibraryContext().store, selector);
}
