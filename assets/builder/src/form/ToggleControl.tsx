import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/** On or off. A toggle with no value shows its default. */
export function ToggleControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const checked = typeof value === 'boolean' ? value : schema.default === true;

    return (
        <input
            type="checkbox"
            id={id}
            className={styles.checkbox}
            checked={checked}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.checked)}
        />
    );
}
