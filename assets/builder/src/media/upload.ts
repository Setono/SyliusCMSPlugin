import type { Translate } from '../i18n';
import type { MediaLimits } from './config';
import type { MediaKind } from './types';

const UNITS = ['B', 'KB', 'MB', 'GB'];

/** A size for editors, as the server writes it: in the largest unit it's at least one of, to one decimal, such as 2.3 MB. */
export function formatSize(bytes: number): string {
    let size = bytes;
    let unit = 0;
    while (size >= 1024 && unit < UNITS.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${String(Number(size.toFixed(1)))} ${UNITS[unit] ?? 'B'}`;
}

/** Why a file can't be uploaded, before it's sent. */
export type ClientRefusal = { reason: 'too_big'; size: number; limit: number } | { reason: 'not_allowed' };

/**
 * Checks a file before uploading it, as the server will: its size against the effective upload limit, and its type
 * against the types the library takes. The browser guesses the type from the name, so a file it can't guess is left for
 * the server to read from its content.
 */
export function checkFile(file: File, limits: MediaLimits): ClientRefusal | null {
    if (file.size > limits.maxUploadSize) {
        return { reason: 'too_big', size: file.size, limit: limits.maxUploadSize };
    }

    if (file.type !== '' && kindOf(file.type, limits) === null) {
        return { reason: 'not_allowed' };
    }

    return null;
}

/** What the library says about a file it refused before sending it, naming the limit, as the server says it. */
export function clientRefusalMessage(t: Translate, filename: string, refusal: ClientRefusal, limits: MediaLimits): string {
    const limit = formatSize(limits.maxUploadSize);

    return refusal.reason === 'too_big'
        ? t('setono_sylius_cms.builder.media.too_big', { '%filename%': filename, '%size%': formatSize(refusal.size), '%limit%': limit })
        : t('setono_sylius_cms.builder.media.not_allowed', { '%filename%': filename, '%types%': typeNames(limits).join(', '), '%limit%': limit });
}

/** What editors call the shipped types, as the server names them. Others are called by their subtype. */
const NAMES: Record<string, string> = {
    'image/jpeg': 'JPEG',
    'image/png': 'PNG',
    'image/gif': 'GIF',
    'image/webp': 'WebP',
    'image/avif': 'AVIF',
    'image/svg+xml': 'SVG',
    'video/mp4': 'MP4',
    'video/webm': 'WebM',
    'application/pdf': 'PDF',
};

/** What editors call the types the library takes, such as JPEG, in the order of the kinds. */
export function typeNames(limits: MediaLimits): string[] {
    const names = Object.values(limits.types)
        .flat()
        .map((mimeType) => NAMES[mimeType] ?? (mimeType.split('/')[1] ?? mimeType).toUpperCase());

    return [...new Set(names)];
}

export function kindOf(mimeType: string, limits: MediaLimits): MediaKind | null {
    for (const [kind, types] of Object.entries(limits.types) as [MediaKind, string[]][]) {
        if (types.includes(mimeType)) {
            return kind;
        }
    }

    return null;
}
