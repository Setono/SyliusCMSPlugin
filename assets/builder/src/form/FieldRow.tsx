import { isMissing, type FieldFallback } from '../fallback';
import { useTranslator } from '../i18n';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';
import { UnsupportedControl } from './UnsupportedControl';
import { controlFor } from './controls';

export type FieldRowProps = Omit<FieldControlProps, 'describedBy' | 'placeholder'> & {
    /** What publish would refuse in the field, shown under it and describing its control. */
    errors?: readonly string[];
    /** What a translatable field shows while it's empty in the edited locale, through the fallback chain. */
    fallback?: FieldFallback | undefined;
};

/**
 * One field rendered from its schema: its label, its field type's control, its help text and its errors. A shared field
 * carries an "All languages" badge, and an empty translatable field shows its fallback value as a placeholder, with
 * "Copy it here". See "Translation aids" in docs/spec/builder.md
 */
export function FieldRow({ errors = [], fallback, ...props }: FieldRowProps) {
    const t = useTranslator();
    const { id, schema, value, onChange } = props;
    const Control = controlFor(schema['x-cms-type']);
    const label = schema['x-cms-label'];
    const help = schema['x-cms-help'];
    const helpId = help ? `${id}-help` : undefined;
    const errorsId = errors.length > 0 ? `${id}-errors` : undefined;
    const placeholder = fallback !== undefined && isMissing(value) ? fallback : undefined;
    const fallbackId = placeholder === undefined ? undefined : `${id}-fallback`;
    const describedBy = [helpId, fallbackId, errorsId].filter((describing) => describing !== undefined).join(' ');

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
                {!schema['x-cms-translatable'] && (
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
