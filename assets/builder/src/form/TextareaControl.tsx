import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/** Text over several lines, with its newlines kept. */
export function TextareaControl({ id, value, onChange, schema, describedBy, placeholder }: FieldControlProps) {
    const maxLength = schema['x-cms-options']?.maxLength;

    return (
        <textarea
            id={id}
            className={styles.textarea}
            rows={4}
            value={typeof value === 'string' ? value : ''}
            placeholder={typeof placeholder === 'string' ? placeholder : undefined}
            maxLength={typeof maxLength === 'number' ? maxLength : undefined}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.value)}
        />
    );
}
