import { useId } from 'react';
import { useBuilder, useBuilderState, useSettingsState, useUrlsState, useValidationState } from '../../context';
import { FieldRow } from '../../form/FieldRow';
import formStyles from '../../form/Form.module.css';
import { useTranslator, type MessageKey } from '../../i18n';
import { fieldFallback, outdatedValue } from '../../locales';
import { locks } from '../../store';
import { entryTranslated, entryValues } from '../../translationState';
import type { FieldSchema } from '../../types';
import { slugMessages } from '../../urls';
import styles from '../SettingsPane.module.css';

export function UrlAndSeoTab() {
    const locked = useBuilderState((state) => locks(state.schedule));

    return (
        <>
            <fieldset className={styles.locked} disabled={locked}>
                <Slug />
            </fieldset>
            <OldUrls />
            <fieldset className={styles.locked} disabled={locked}>
                <MetaField field="metaTitle" type="text" label="setono_sylius_cms.builder.meta_title" help="setono_sylius_cms.builder.meta_title_help" />
                <MetaField field="metaDescription" type="textarea" label="setono_sylius_cms.builder.meta_description" help="setono_sylius_cms.builder.meta_description_help" />
                <Noindex />
            </fieldset>
        </>
    );
}

/**
 * The edited locale's meta title or meta description. While it's empty, it shows the value the fallback chain gives it,
 * which the head tags print; past the chain, the meta title falls back to the title and the meta description to the
 * field its content type names. The head prints it whatever the layout. See "SEO fields" in docs/spec/urls-and-seo.md
 */
function MetaField({ field, type, label, help }: { field: 'metaTitle' | 'metaDescription'; type: 'text' | 'textarea'; label: MessageKey; help: MessageKey }) {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const translations = useBuilderState((state) => state.entry.translations);
    const values = entryValues({ translations }, field);
    const schema: FieldSchema = {
        'x-cms-type': type,
        'x-cms-label': t(label),
        'x-cms-help': t(help),
        'x-cms-required': false,
        'x-cms-translatable': true,
    };

    return (
        <FieldRow
            id={`cms-entry-${field}`}
            schema={schema}
            locale={locale}
            value={values[locale] ?? undefined}
            onChange={(value) => store.getState().setEntryValue(field, value)}
            fallback={fieldFallback(locales, values, locale)}
            outdated={outdatedValue(locales, values, entryTranslated({ translations }, field), locale, () => store.getState().markEntryValueUpToDate(field))}
        />
    );
}

/** "Hide from search engines", which all translations share. It's drafted. */
function Noindex() {
    const t = useTranslator();
    const { store } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const noindex = useBuilderState((state) => state.entry.fields.noindex);
    const schema: FieldSchema = {
        'x-cms-type': 'toggle',
        'x-cms-label': t('setono_sylius_cms.builder.noindex'),
        'x-cms-help': t('setono_sylius_cms.builder.noindex_help'),
        'x-cms-required': false,
        'x-cms-translatable': false,
        default: false,
    };

    return (
        <FieldRow
            id="cms-entry-noindex"
            schema={schema}
            locale={locale}
            value={noindex}
            onChange={(value) => store.getState().setSharedEntryValue('noindex', value)}
        />
    );
}

/**
 * The edited locale's slug, with the URL it gives, and why the URL can't be published or saved: the errors publish
 * would refuse, a slug the server didn't save because another route takes its URL, and warnings, such as another
 * entry that has drafted the URL too. A slug never falls back. See "Slugs" in docs/spec/urls-and-seo.md
 */
function Slug() {
    const t = useTranslator();
    const { store, urlPrefix } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const slug = useBuilderState((state) => state.entry.translations[state.locale]?.fields.slug);
    const refused = useBuilderState((state) => state.refusedSlugs[state.locale]);
    const errors = useValidationState((state) => state.errors);
    const warnings = useUrlsState((state) => state.warnings);
    // A channel's homepage shows at the channel's root, where its own URL redirects in that channel
    const homepage = useSettingsState((state) => state.settings.homepageOf.length > 0);
    const url = typeof slug === 'string' && slug !== '' ? `/${locale}/${urlPrefix === null || urlPrefix === '' ? '' : `${urlPrefix}/`}${slug}` : null;
    const warned = slugMessages(warnings, locale);
    const help =
        url === null
            ? t('setono_sylius_cms.builder.slug_help_none')
            : homepage
              ? t('setono_sylius_cms.builder.slug_help_homepage', { '%homepage%': `/${locale}/`, '%url%': url })
              : t('setono_sylius_cms.builder.slug_help', { '%url%': url });
    const schema: FieldSchema = {
        'x-cms-type': 'text',
        'x-cms-label': t('setono_sylius_cms.builder.slug'),
        'x-cms-help': help,
        'x-cms-required': true,
        'x-cms-translatable': true,
    };

    return (
        <div className={styles.setting}>
            <FieldRow
                id="cms-entry-slug"
                schema={schema}
                locale={locale}
                value={slug ?? undefined}
                onChange={(value) => store.getState().setEntryValue('slug', value)}
                errors={[...(refused === undefined ? [] : [refused]), ...slugMessages(errors, locale)]}
            />
            {warned.length === 0 ? null : (
                <ul className={styles.warnings}>
                    {warned.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** The URLs of the edited locale's old slugs, which redirect to its URL. See "Old slugs" in docs/spec/urls-and-seo.md */
function OldUrls() {
    const t = useTranslator();
    const headingId = useId();
    const locale = useBuilderState((state) => state.locale);
    const oldUrls = useUrlsState((state) => state.oldUrls)[locale] ?? [];

    if (oldUrls.length === 0) {
        return null;
    }

    return (
        <div className={styles.setting}>
            <h3 id={headingId} className={formStyles.label}>
                {t('setono_sylius_cms.builder.old_urls')}
            </h3>
            <p className={formStyles.help}>{t('setono_sylius_cms.builder.old_urls_help')}</p>
            <ul className={styles.oldUrls} aria-labelledby={headingId}>
                {oldUrls.map((oldUrl) => (
                    <li key={oldUrl}>
                        <code>{oldUrl}</code>
                    </li>
                ))}
            </ul>
        </div>
    );
}
