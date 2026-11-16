import { useBuilder, useBuilderState } from '../context';
import { isMissing } from '../fallback';
import { fingerprint } from '../fingerprint';
import { useTranslator } from '../i18n';
import { hasTranslation, localeOf, sourceLocale } from '../locales';
import type { FieldValues, TranslationSource } from '../types';
import styles from './TranslationBanner.module.css';

/**
 * Shows when the entry has no translation in the edited locale, with a way to add one. The new translation starts from
 * the title and slug of the translation the locale falls back to, for the editor to translate, so its title is recorded
 * as translated from there. See "Translation aids"
 * in docs/spec/builder.md
 */
export function TranslationBanner() {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const locale = localeOf(locales, useBuilderState((state) => state.locale));
    const translated = useBuilderState((state) => hasTranslation(state.entry, state.locale));

    if (translated) {
        return null;
    }

    const add = (): void => {
        const { entry } = store.getState();
        const source = sourceLocale(entry, locale);
        const fields: FieldValues = {};
        for (const field of ['slug', 'title']) {
            const value = source === undefined ? undefined : entry.translations[source]?.fields[field];
            if (value !== undefined) {
                fields[field] = value;
            }
        }

        // The title is copied to be translated, as "Copy it here" copies it, so it's translated from there. A slug never falls back
        const title = fields.title;
        const translated: Record<string, TranslationSource> = source === undefined || title === undefined || isMissing(title) ? {} : { title: { from: source, fingerprint: fingerprint(title) } };

        store.getState().addTranslation(fields, translated);
    };

    return (
        <div className={styles.banner}>
            <p className={styles.message}>{t('setono_sylius_cms.builder.no_translation', { '%locale%': locale.name })}</p>
            <button type="button" className={styles.add} onClick={add}>
                {t('setono_sylius_cms.builder.add_translation', { '%locale%': locale.name })}
            </button>
        </div>
    );
}
