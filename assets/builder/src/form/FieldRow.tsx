import { acceptedTypes, bindsWholeValue, isBinding, newBinding } from '../bindings';
import { useOfferedProperties } from '../bindingsContext';
import { isMissing, type FieldFallback } from '../fallback';
import { useTranslator } from '../i18n';
import { keepsPartsPerLocale } from '../links';
import type { OutdatedValue } from '../translationState';
import type { JsonValue } from '../types';
import { plainText } from '../values';
import { BindingPicker } from './BindingPicker';
import bindingStyles from './Bindings.module.css';
import { BoundField } from './BoundField';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';
import { UnsupportedControl } from './UnsupportedControl';
import { controlFor } from './controls';

/** What a field that can be bound as a whole needs to show and set a binding. */
export interface FieldBinding {
    /** What the field stores: a binding, or its value, which for a translatable field is a locale map. */
    stored: JsonValue | undefined;
    /** Sets what the field stores, whole: a binding, or once it's unbound, what the binding fell back to. */
    set: (value: JsonValue | null) => void;
}

const NONE: readonly string[] = [];

export type FieldRowProps = Omit<FieldControlProps, 'describedBy' | 'placeholder'> & {
    /** What publish would refuse in the field, shown under it and describing its control. */
    errors?: readonly string[];
    /** What a translatable field shows while it's empty in the edited locale, through the fallback chain. */
    fallback?: FieldFallback | undefined;
    /** Given when the value, or a bound field's fallback, is outdated: the value it was translated from has changed since. */
    outdated?: OutdatedValue | undefined;
    /** Whether a shared field carries the "All languages" badge. Fields that are always shared, such as a condition's, leave it out. */
    languageBadge?: boolean;
    /** Given where the field can show a binding, as a block's can: a ⚡ button beside it binds it, when its field type can be bound. */
    binding?: FieldBinding | undefined;
};

/**
 * One field rendered from its schema: its label, its field type's control, its help text and its errors. A shared field
 * carries an "All languages" badge, an empty translatable field shows its fallback value as a placeholder, with
 * "Copy it here", and an outdated field shows the current text of its source, with "Mark as up to date". See
 * "Translation aids" in docs/spec/builder.md. A field that can be bound has a ⚡ button, and a bound one shows its
 * binding instead of its control. See "Bindings" in docs/spec/builder.md
 */
export function FieldRow({ errors = [], fallback, outdated, languageBadge = true, binding, ...props }: FieldRowProps) {
    const t = useTranslator();
    const { id, schema, value, onChange } = props;
    const Control = controlFor(schema);
    const bindable = binding !== undefined && bindsWholeValue(schema);
    const offered = useOfferedProperties(bindable ? acceptedTypes(schema) : NONE);
    const bound = bindable && isBinding(binding.stored) ? binding.stored : null;
    const label = schema['x-cms-label'];
    const help = schema['x-cms-help'];
    const helpId = help ? `${id}-help` : undefined;
    const errorsId = errors.length > 0 ? `${id}-errors` : undefined;
    const placeholder = bound === null && fallback !== undefined && isMissing(value) ? fallback : undefined;
    const fallbackId = placeholder === undefined ? undefined : `${id}-fallback`;
    // A bound field's fallback is translated like its value, so it's flagged outdated the same way
    const outdatedId = outdated === undefined ? undefined : `${id}-outdated`;
    const describedBy = [helpId, fallbackId, outdatedId, errorsId].filter((describing) => describing !== undefined).join(' ');

    return (
        <div className={styles.field}>
            <div className={styles.labelRow}>
                {Control ? (
                    // Its id names a control that isn't a form control, such as a project's own field control
                    <label id={`${id}-row-label`} htmlFor={id} className={styles.label}>
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
                {binding !== undefined && (offered.length > 0 || bound !== null) && (
                    <BindingPicker
                        offered={offered}
                        // Named apart from the field, so the field's label names only its control; described by the label
                        label={t('setono_sylius_cms.builder.binding.bind')}
                        describedBy={`${id}-row-label`}
                        className={bindingStyles.labelRowBind}
                        // What the field holds, typed or the binding's fallback, becomes the new binding's fallback
                        onPick={(property) => binding.set(newBinding(property.binding, bound === null ? (binding.stored ?? null) : bound.$fallback))}
                    />
                )}
            </div>
            {bound !== null ? (
                <BoundField id={id} schema={schema} locale={props.locale} binding={bound} onChange={binding?.set ?? onChange} placeholder={fallback} describedBy={describedBy === '' ? undefined : describedBy} />
            ) : Control ? (
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
