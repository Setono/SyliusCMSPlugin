import type { MediaLibraryConfig } from '../../src/media/config';
import type { MediaItem } from '../../src/media/types';

export const libraryConfig: MediaLibraryConfig = {
    csrfToken: 'abc123',
    endpoints: { media: '/admin/cms/api/media', folders: '/admin/cms/api/media-folders' },
    locales: [
        { code: 'en_US', name: 'English (United States)' },
        { code: 'de_DE', name: 'German (Germany)' },
    ],
    media: {
        maxUploadSize: 2 * 1024 * 1024,
        types: {
            image: ['image/jpeg', 'image/png', 'image/svg+xml'],
            video: ['video/mp4'],
            document: ['application/pdf'],
        },
    },
    strings: {
        'setono_sylius_cms.builder.media.all_media': 'All media',
        'setono_sylius_cms.builder.media.unsorted': 'Unsorted',
        'setono_sylius_cms.builder.media.folders': 'Folders',
        'setono_sylius_cms.builder.media.new_folder': 'New folder',
        'setono_sylius_cms.builder.media.folder_name': 'Folder name',
        'setono_sylius_cms.builder.media.create_folder': 'Create',
        'setono_sylius_cms.builder.media.rename_folder': 'Rename folder',
        'setono_sylius_cms.builder.media.move_folder': 'Move folder',
        'setono_sylius_cms.builder.media.move_to': 'Move to',
        'setono_sylius_cms.builder.media.move': 'Move',
        'setono_sylius_cms.builder.media.top_level': 'At the top',
        'setono_sylius_cms.builder.media.cancel': 'Cancel',
        'setono_sylius_cms.builder.media.search': 'Search',
        'setono_sylius_cms.builder.media.kind': 'Kind',
        'setono_sylius_cms.builder.media.any_kind': 'Any kind',
        'setono_sylius_cms.builder.media.kind_image': 'Images',
        'setono_sylius_cms.builder.media.kind_video': 'Videos',
        'setono_sylius_cms.builder.media.kind_document': 'Documents',
        'setono_sylius_cms.builder.media.missing_alt': 'Missing alt text',
        'setono_sylius_cms.builder.media.missing_alt_flag': 'No alt text',
        'setono_sylius_cms.builder.media.upload': 'Upload',
        'setono_sylius_cms.builder.media.drop_here': 'Drop files to upload them into %folder%',
        'setono_sylius_cms.builder.media.duplicate': '%filename% is already in the library as %name%.',
        'setono_sylius_cms.builder.media.use_existing': 'Use the existing one',
        'setono_sylius_cms.builder.media.upload_copy': 'Upload a copy',
        'setono_sylius_cms.builder.media.too_big': '%filename% is %size%, over the upload limit of %limit%.',
        'setono_sylius_cms.builder.media.not_allowed': "%filename% is a type of file the media library doesn't take. It takes %types%, up to %limit% each.",
        'setono_sylius_cms.builder.media.name': 'Name',
        'setono_sylius_cms.builder.media.credit': 'Credit',
        'setono_sylius_cms.builder.media.alt_text': 'Alt text',
        'setono_sylius_cms.builder.media.focal_point': 'Focal point',
        'setono_sylius_cms.builder.media.save': 'Save',
        'setono_sylius_cms.builder.media.saved': 'Saved',
        'setono_sylius_cms.builder.media.no_media': 'Nothing here yet.',
        'setono_sylius_cms.builder.media.no_matches': 'No media items match.',
    },
};

export function mediaItem(uuid: string, overrides: Partial<MediaItem> = {}): MediaItem {
    return {
        uuid,
        kind: 'image',
        name: uuid,
        originalFilename: `${uuid}.png`,
        mimeType: 'image/png',
        size: 1234,
        width: 40,
        height: 20,
        credit: null,
        altTexts: {},
        missingAlt: true,
        focalPoint: { x: 0.5, y: 0.5 },
        poster: null,
        folder: null,
        url: `/media/cms/${uuid}/abc/${uuid}.png`,
        createdAt: '2026-11-16T10:00:00Z',
        changedAt: '2026-11-16T10:00:00Z',
        ...overrides,
    };
}

export function file(name: string, type: string, size = 100): File {
    return new File([new Uint8Array(size)], name, { type });
}
