import styles from './Form.module.css';
import { LocalDateTimeInput } from './LocalDateTimeInput';
import type { FieldControlProps } from './types';

/**
 * A date and time, stored in UTC, which editors enter in the time zone the server gives. It changes the value once the
 * input holds a whole date and time, or nothing.
 */
export function DateTimeControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    return (
        <LocalDateTimeInput
            id={id}
            className={styles.input}
            value={isUtc(value) ? value : null}
            commitOn="change"
            onCommit={onChange}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
        />
    );
}

function isUtc(value: unknown): value is string {
    return typeof value === 'string' && !Number.isNaN(new Date(value).getTime());
}
