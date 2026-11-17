import { isMissing, type FieldFallback } from '../fallback';
import { useTranslator } from '../i18n';
import { keepsPartsPerLocale } from '../links';
import type { OutdatedValue } from '../translationState';
import { plainText } from '../values';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';
import { UnsupportedControl } from './UnsupportedControl';
import { controlFor } from './controls';

export type FieldRowProps = Omit<FieldControlProps, 'describedBy' | 'placeholder'> & {
    /** What publish would refuse in the field, shown under it and describing its control. */
    errors?: readonly string[];
    /** What a translatable field shows while it's empty in the edited locale, through the fallback chain. */
    fallback?: FieldFallback | undefined;
    /** Given when the value is outdated: the value it was translated from has changed since. */
    outdated?: OutdatedValue | undefined;
    /** Whether a shared field carries the "All languages" badge. Fields that are always shared, such as a condition's, leave it out. */
    languageBadge?: boolean;
};

/**
 * One field rendered from its schema: its label, its field type's control, its help text and its errors. A shared field
 * carries an "All languages" badge, an empty translatable field shows its fallback value as a placeholder, with
 * "Copy it here", and an outdated field shows the current text of its source, with "Mark as up to date". See
 * "Translation aids" in docs/spec/builder.md
 */
export function FieldRow({ errors = [], fallback, outdated, languageBadge = true, ...props }: FieldRowProps) {
    const t = useTranslator();
    const { id, schema, value, onChange } = props;
    const Control = controlFor(schema['x-cms-type']);
    const label = schema['x-cms-label'];
    const help = schema['x-cms-help'];
    const helpId = help ? `${id}-help` : undefined;
    const errorsId = errors.length > 0 ? `${id}-errors` : undefined;
    const placeholder = fallback !== undefined && isMissing(value) ? fallback : undefined;
    const fallbackId = placeholder === undefined ? undefined : `${id}-fallback`;
    const outdatedId = outdated === undefined ? undefined : `${id}-outdated`;
    const describedBy = [helpId, fallbackId, outdatedId, errorsId].filter((describing) => describing !== undefined).join(' ');

    return (
        <div className={styles.field}>
            <div className={styles.labelRow}>
                {Control ? (
                    <label htmlFor={id} className={styles.label}>
                        {label}
                    </label>
                ) : (
                    <span className={styles.label}>{label}</span>
                )}
                {schema['x-cms-required'] && (
                    <span className={styles.required} aria-hidden="true">
                        *
                    </span>
                )}
                {languageBadge && !schema['x-cms-translatable'] && !keepsPartsPerLocale(schema) && (
                    <span className={styles.badge} title={t('setono_sylius_cms.builder.all_languages_help')}>
                        {t('setono_sylius_cms.builder.all_languages')}
                    </span>
                )}
            </div>
            {Control ? (
                <Control {...props} placeholder={placeholder?.value} describedBy={describedBy === '' ? undefined : describedBy} />
            ) : (
                <UnsupportedControl id={id} />
            )}
            {placeholder === undefined ? null : (
                <p id={fallbackId} className={styles.fallback}>
                    {t('setono_sylius_cms.builder.falls_back_to', { '%locale%': placeholder.localeName })}
                    <button type="button" className={styles.copy} onClick={() => onChange(placeholder.value)}>
                        {t('setono_sylius_cms.builder.copy_it_here')}
                    </button>
                </p>
            )}
            {outdated === undefined ? null : (
                <div id={outdatedId} className={styles.outdated}>
                    <p className={styles.outdatedMessage}>{t('setono_sylius_cms.builder.outdated', { '%locale%': outdated.localeName })}</p>
                    <blockquote className={styles.source}>{plainText(outdated.value)}</blockquote>
                    <button type="button" className={styles.copy} onClick={outdated.onMarkUpToDate}>
                        {t('setono_sylius_cms.builder.mark_up_to_date')}
                    </button>
                </div>
            )}
            {help ? (
                <p id={helpId} className={styles.help}>
                    {help}
                </p>
            ) : null}
            {errorsId === undefined ? null : (
                <ul id={errorsId} className={styles.errors}>
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
