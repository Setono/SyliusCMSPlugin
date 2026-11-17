import { useId } from 'react';
import { MediaItemSummary } from '../form/MediaItemSummary';
import { useTranslator } from '../i18n';
import styles from './MediaLibrary.module.css';
import { useKnownMediaItems, useMediaPicker } from './picker';

/**
 * A video's poster in its details: the image it holds, by its thumbnail and name, picked through the library's own
 * picker, or removed. The details save it with the rest. See "Metadata" in docs/spec/media.md
 */
export function PosterControl({ value, onChange }: { value: string | null; onChange: (poster: string | null) => void }) {
    const t = useTranslator();
    const id = useId();
    const picker = useMediaPicker();
    const known = useKnownMediaItems(value === null ? [] : [value]);

    const pick = async (): Promise<void> => {
        const picked = await picker?.pickMedia({ kinds: ['image'], multiple: false });
        const first = picked?.[0];
        if (first !== undefined) {
            onChange(first.uuid);
        }
    };

    return (
        <div role="group" className={styles.fieldset} aria-labelledby={`${id}-label`} aria-describedby={`${id}-help`}>
            <span id={`${id}-label`} className={styles.label}>
                {t('setono_sylius_cms.builder.media.poster')}
            </span>
            <span id={`${id}-help`} className={styles.help}>
                {t('setono_sylius_cms.builder.media.poster_help')}
            </span>
            {value === null ? <p className={styles.help}>{t('setono_sylius_cms.builder.media.nothing_picked')}</p> : <MediaItemSummary item={known[value]} />}
            <div className={styles.actions}>
                <button type="button" className={styles.secondary} disabled={picker === null} onClick={() => void pick()}>
                    {value === null ? t('setono_sylius_cms.builder.media.pick') : t('setono_sylius_cms.builder.media.replace')}
                </button>
                {value !== null && (
                    <button type="button" className={styles.secondary} onClick={() => onChange(null)}>
                        {t('setono_sylius_cms.builder.media.remove')}
                    </button>
                )}
            </div>
        </div>
    );
}
