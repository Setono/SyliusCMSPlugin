import { useTranslator } from '../i18n';
import { useKnownMediaItems, useMediaPicker } from '../media/picker';
import { MediaItemSummary, mediaOptions } from './MediaItemSummary';
import styles from './Picked.module.css';
import type { FieldControlProps } from './types';

/**
 * A Media field: the media item it holds, by its name, and a button that opens the media library as a picker of the
 * kinds the field takes, which the field's label names. See "Media library" in docs/spec/admin.md
 */
export function MediaControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const t = useTranslator();
    const picker = useMediaPicker();
    const uuid = typeof value === 'string' && value !== '' ? value : null;
    const known = useKnownMediaItems(uuid === null ? [] : [uuid]);
    const { kinds } = mediaOptions(schema);

    const pick = async (): Promise<void> => {
        const picked = await picker?.pickMedia({ kinds, multiple: false });
        const first = picked?.[0];
        if (first !== undefined) {
            onChange(first.uuid);
        }
    };

    return (
        <div className={styles.control}>
            {uuid === null ? <p className={styles.nothing}>{t('setono_sylius_cms.builder.media.nothing_picked')}</p> : <MediaItemSummary item={known[uuid]} />}
            <div className={styles.actions}>
                <button id={id} type="button" className={styles.button} aria-describedby={describedBy} disabled={picker === null} onClick={() => void pick()}>
                    {uuid === null ? t('setono_sylius_cms.builder.media.pick') : t('setono_sylius_cms.builder.media.replace')}
                </button>
                {uuid !== null && (
                    <button type="button" className={styles.button} onClick={() => onChange(null)}>
                        {t('setono_sylius_cms.builder.media.remove')}
                    </button>
                )}
            </div>
        </div>
    );
}
