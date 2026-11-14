import styles from './Form.module.css';
import type { FieldWidgetProps } from './types';

export function TextWidget({ id, value, onChange, schema, describedBy }: FieldWidgetProps) {
    const maxLength = schema['x-cms-options']?.maxLength;

    return (
        <input
            type="text"
            id={id}
            className={styles.input}
            value={typeof value === 'string' ? value : ''}
            maxLength={typeof maxLength === 'number' ? maxLength : undefined}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.value)}
        />
    );
}
