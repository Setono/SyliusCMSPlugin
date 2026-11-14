import styles from './Form.module.css';
import type { FieldWidgetProps } from './types';

/** On or off. A toggle with no value shows its default. */
export function ToggleWidget({ id, value, onChange, schema, describedBy }: FieldWidgetProps) {
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
