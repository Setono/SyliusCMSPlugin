import { useId, type ReactNode } from 'react';
import styles from './MediaLibrary.module.css';

interface FieldProps {
    label: string;
    help?: string;
    /** The label's look: a field's name, or a locale's under a fieldset's legend. */
    variant?: 'label' | 'locale';
    className?: string;
    children: (id: string) => ReactNode;
}

/** A label, the control it names, and help below it, as the builder's own fields have them. */
export function Field({ label, help, variant = 'label', className, children }: FieldProps) {
    const id = useId();

    return (
        <div className={className ?? styles.field}>
            <label htmlFor={id} className={variant === 'label' ? styles.label : styles.locale}>
                {label}
            </label>
            {children(id)}
            {help !== undefined && <span className={styles.help}>{help}</span>}
        </div>
    );
}
