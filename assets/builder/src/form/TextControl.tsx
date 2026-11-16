import styles from './Form.module.css';
import type { FieldControlProps } from './types';

export function TextControl({ id, value, onChange, schema, describedBy, placeholder }: FieldControlProps) {
    const maxLength = schema['x-cms-options']?.maxLength;

    return (
        <input
            type="text"
            id={id}
            className={styles.input}
            value={typeof value === 'string' ? value : ''}
            placeholder={typeof placeholder === 'string' ? placeholder : undefined}
            maxLength={typeof maxLength === 'number' ? maxLength : undefined}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.value)}
        />
    );
}
