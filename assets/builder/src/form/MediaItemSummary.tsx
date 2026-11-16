import { useTranslator } from '../i18n';
import { MEDIA_KINDS, type MediaItem, type MediaKind } from '../media/types';
import type { FieldSchema } from '../types';
import { isJsonObject } from '../values';
import styles from './MediaControl.module.css';

/** The kinds of media item a Media or Media list field takes, and the most a media list holds, from its schema. */
export function mediaOptions(schema: FieldSchema): { kinds: MediaKind[]; max: number | null } {
    const options = isJsonObject(schema['x-cms-options']) ? schema['x-cms-options'] : {};
    const listed = Array.isArray(options.kinds) ? options.kinds : [];
    const kinds = MEDIA_KINDS.filter((kind) => listed.includes(kind));

    return { kinds: kinds.length > 0 ? kinds : ['image'], max: typeof options.max === 'number' ? options.max : null };
}

/**
 * A media item a field holds, by its thumbnail and name: undefined while it's looked up, and null for one that was
 * deleted, which the shop doesn't show.
 */
export function MediaItemSummary({ item }: { item: MediaItem | null | undefined }) {
    const t = useTranslator();

    if (item === undefined) {
        return <span className={styles.summary} aria-busy="true" />;
    }

    if (item === null) {
        return <span className={`${styles.summary} ${styles.deleted}`}>{t('setono_sylius_cms.builder.media.deleted_media_item')}</span>;
    }

    return (
        <span className={styles.summary}>
            {item.kind === 'image' ? (
                <img className={styles.thumbnail} src={item.url} alt="" />
            ) : (
                <span className={styles.fileThumbnail}>{(item.url.split('.').pop() ?? '').toUpperCase()}</span>
            )}
            <span className={styles.name}>{item.name}</span>
        </span>
    );
}
