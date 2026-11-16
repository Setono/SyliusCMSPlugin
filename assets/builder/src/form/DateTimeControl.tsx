import { useEffect, useState } from 'react';
import { fromLocalInput, toLocalInput } from '../dates';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/**
 * A date and time, stored in UTC, which editors enter in their own time zone. It changes the value once the input holds
 * a whole date and time, or nothing.
 */
export function DateTimeControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const stored = isUtc(value) ? value : null;
    const [local, setLocal] = useState(toLocalInput(stored));

    // Shows what's stored, such as after an undo
    useEffect(() => setLocal(toLocalInput(stored)), [stored]);

    return (
        <input
            type="datetime-local"
            id={id}
            className={styles.input}
            value={local}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => {
                const next = event.currentTarget.value;
                setLocal(next);

                const utc = fromLocalInput(next);
                if (next === '' || utc !== null) {
                    onChange(utc);
                }
            }}
        />
    );
}

function isUtc(value: unknown): value is string {
    return typeof value === 'string' && !Number.isNaN(new Date(value).getTime());
}
