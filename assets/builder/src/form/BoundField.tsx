import { bindingOf, fallbackIn, propertyOf, withFallback, withParam, type StoredBinding } from '../bindings';
import { useBindableProperties } from '../bindingsContext';
import type { FieldFallback } from '../fallback';
import { useTranslator } from '../i18n';
import type { FieldSchema, JsonValue } from '../types';
import styles from './Bindings.module.css';
import { controlFor } from './controls';
import { FieldRow } from './FieldRow';
import formStyles from './Form.module.css';

export interface BoundFieldProps {
    /** The field's control's id, which the bound field takes its parts' ids from. */
    id: string;
    schema: FieldSchema;
    locale: string;
    binding: StoredBinding;
    /** Called with the changed binding, or with what the field stores once it's unbound. */
    onChange: (value: JsonValue | null) => void;
    /** What the fallback shows while it's empty in the edited locale, through the fallback chain. */
    placeholder?: FieldFallback | undefined;
    describedBy?: string | undefined;
}

/**
 * A field that shows a binding: a chip with the property's label, which unbinds it, the property's parameters, and
 * the fallback shown when its value is empty, entered with the field's own control and translated like the field.
 * Unbound, the field keeps its fallback as its value. See "Bindings" in docs/spec/builder.md
 */
export function BoundField({ id, schema, locale, binding: stored, onChange, placeholder, describedBy }: BoundFieldProps) {
    const t = useTranslator();
    const binding = bindingOf(stored);
    const property = propertyOf(useBindableProperties(), binding.$bind);
    const translatable = schema['x-cms-translatable'];
    const Control = controlFor(schema);
    const label = property?.label ?? binding.$bind;

    return (
        <div className={styles.bound} role="group" aria-label={t('setono_sylius_cms.builder.binding.bound', { '%property%': label })} aria-describedby={describedBy}>
            <div className={styles.chipRow}>
                <span className={styles.chip} data-binding={binding.$bind}>
                    <span aria-hidden="true">⚡</span>
                    {label}
                </span>
                <button type="button" className={styles.unbind} aria-label={t('setono_sylius_cms.builder.binding.unbind', { '%property%': label })} onClick={() => onChange(binding.$fallback)}>
                    ✕
                </button>
            </div>
            {property === undefined && <p className={formStyles.help}>{t('setono_sylius_cms.builder.binding.removed')}</p>}
            {property?.fields.map(({ name, schema: paramSchema }) => (
                <FieldRow
                    key={name}
                    id={`${id}-param-${name}`}
                    schema={paramSchema}
                    locale={locale}
                    value={binding.$params[name]}
                    onChange={(value) => onChange(withParam(binding, name, value))}
                    languageBadge={false}
                />
            ))}
            {Control !== undefined && (
                <div className={styles.part}>
                    <label htmlFor={`${id}-fallback`} className={styles.partLabel}>
                        {t('setono_sylius_cms.builder.binding.fallback')}
                    </label>
                    <Control
                        id={`${id}-fallback`}
                        schema={{ ...schema, 'x-cms-required': false, 'x-cms-label': t('setono_sylius_cms.builder.binding.fallback') }}
                        locale={locale}
                        value={fallbackIn(binding, translatable, locale)}
                        onChange={(value) => onChange(withFallback(binding, translatable, locale, value))}
                        placeholder={placeholder?.value}
                        describedBy={`${id}-fallback-help`}
                    />
                    <p id={`${id}-fallback-help`} className={formStyles.help}>
                        {t('setono_sylius_cms.builder.binding.fallback_help')}
                    </p>
                </div>
            )}
        </div>
    );
}
