import type { ReactNode } from 'react';
import { withParam, type BindablePropertyDefinition, type StoredBinding } from '../bindings';
import { useTranslator } from '../i18n';
import styles from './Bindings.module.css';
import { FieldRow } from './FieldRow';
import formStyles from './Form.module.css';

/**
 * The parts a binding is edited in, whether it's a bound field's or a token's in rich text. See "Bindings" in
 * docs/spec/builder.md
 */

/** The bound property's parameters, each through its field type's control. Their ids start with the id given. */
export function BindingParams({
    id,
    property,
    binding,
    locale,
    onChange,
}: {
    id: string;
    /** The bound property, or undefined for one the code no longer has, which has none. */
    property: BindablePropertyDefinition | undefined;
    binding: StoredBinding;
    locale: string;
    onChange: (binding: StoredBinding) => void;
}) {
    return (
        <>
            {property?.fields.map(({ name, schema }) => (
                <FieldRow
                    key={name}
                    id={`${id}-param-${name}`}
                    schema={schema}
                    locale={locale}
                    value={binding.$params[name]}
                    onChange={(value) => onChange(withParam(binding, name, value))}
                    languageBadge={false}
                />
            ))}
        </>
    );
}

/**
 * The fallback a binding shows when its value is empty: the control given, with the id given, labelled and explained.
 * The explanation's id is the control's with -help, which the control is described by.
 */
export function BindingFallback({ id, children }: { id: string; children: ReactNode }) {
    const t = useTranslator();

    return (
        <div className={styles.part}>
            <label htmlFor={id} className={styles.partLabel}>
                {t('setono_sylius_cms.builder.binding.fallback')}
            </label>
            {children}
            <p id={`${id}-help`} className={formStyles.help}>
                {t('setono_sylius_cms.builder.binding.fallback_help')}
            </p>
        </div>
    );
}
