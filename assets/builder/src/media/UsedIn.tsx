import { useId } from 'react';
import { useTranslator, type Translate } from '../i18n';
import { useMediaLibrary } from './context';
import styles from './MediaLibrary.module.css';
import type { MediaUse } from './types';

/**
 * A media item's Used in list, as the usage index has it: each entry that uses it, linked to its overview page, with
 * its content type and whether its published content or only its draft uses it. See "Usage index" in docs/spec/media.md.
 */
export function UsedIn({ uuid }: { uuid: string }) {
    const t = useTranslator();
    const uses = useMediaLibrary((state) => (state.uses?.uuid === uuid ? state.uses : null));
    const id = useId();

    return (
        <section className={styles.usedIn} aria-busy={uses === null || (uses.list === null && !uses.failed)}>
            <h3 id={id} className={styles.label}>
                {t('setono_sylius_cms.builder.media.used_in')}
            </h3>
            {uses?.failed === true && (
                <p className={styles.refused} role="alert">
                    {t('setono_sylius_cms.builder.media.uses_load_failed')}
                </p>
            )}
            {uses?.list?.length === 0 && <p className={styles.help}>{t('setono_sylius_cms.builder.media.used_nowhere')}</p>}
            {uses?.list !== undefined && uses.list !== null && uses.list.length > 0 && (
                <ul className={styles.uses} aria-labelledby={id}>
                    {uses.list.map((use) => (
                        <li key={use.entry}>
                            <a href={use.url}>{use.title ?? t('setono_sylius_cms.builder.media.untitled')}</a>
                            <span className={styles.help}>{`${use.contentType} · ${whereUsed(t, use)}`}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** Where an entry uses the media item: in what shoppers see, or only in its draft. */
function whereUsed(t: Translate, use: MediaUse): string {
    if (!use.published) {
        return t('setono_sylius_cms.builder.media.use_draft');
    }

    return use.draft ? t('setono_sylius_cms.builder.media.use_published') : t('setono_sylius_cms.builder.media.use_removed_in_draft');
}
