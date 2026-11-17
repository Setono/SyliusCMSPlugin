import { readDataConfig } from '../dataConfig';
import type { MediaFilters, MediaKind } from './types';

/** A locale of the store, which media items have alt text in. */
export interface MediaLocale {
    code: string;
    /** In the admin user's locale. */
    name: string;
}

/** The effective upload limit and the MIME types of each kind, so a file is checked before it's uploaded. */
export interface MediaLimits {
    /** In bytes. */
    maxUploadSize: number;
    types: Record<MediaKind, string[]>;
}

/**
 * What the media library needs, as JSON in the data-config attribute of #cms-media-library on its own screen. The
 * builder's media picker makes it from the builder's config and registry.
 */
export interface MediaLibraryConfig {
    csrfToken: string;
    endpoints: {
        /** GET lists, POST uploads, and PATCH on /{uuid} saves one. */
        media: string;
        /** GET lists, POST creates, and PATCH on /{uuid} renames or moves one. */
        folders: string;
    };
    /** The store's locales, the default first. */
    locales: MediaLocale[];
    media: MediaLimits;
    strings: Record<string, string>;
    /** What the library on its own screen shows first, such as only the images missing alt text, which the Overview links to. */
    filters?: Pick<Partial<MediaFilters>, 'missingAlt'>;
}

export function readMediaLibraryConfig(element: HTMLElement): MediaLibraryConfig {
    return readDataConfig<MediaLibraryConfig>(element, 'The media library needs its configuration in the data-config attribute.');
}
