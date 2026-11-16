import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/** A calendar day, stored as 2026-11-13, which a date input reads and writes as it is. */
export function DateControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    return (
        <input
            type="date"
            id={id}
            className={styles.input}
            value={typeof value === 'string' ? value : ''}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.value)}
        />
    );
}
