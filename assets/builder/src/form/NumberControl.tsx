import type { JsonValue } from '../types';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/**
 * A number between the field's bounds, in its steps. A field that takes whole numbers moves in whole steps. An empty
 * field shows its default, or the value it falls back to, and clearing it removes the value.
 */
export function NumberControl({ id, value, onChange, schema, describedBy, placeholder }: FieldControlProps) {
    const options = schema['x-cms-options'];
    const integer = options?.integer === true;
    const step = numberOrNull(options?.step);
    const shown = numberOrNull(placeholder) ?? numberOrNull(schema.default);

    return (
        <input
            type="number"
            id={id}
            className={styles.input}
            value={typeof value === 'number' ? value : ''}
            min={numberOrNull(options?.min) ?? undefined}
            max={numberOrNull(options?.max) ?? undefined}
            step={step ?? (integer ? 1 : 'any')}
            inputMode={integer ? 'numeric' : 'decimal'}
            placeholder={shown === null ? undefined : String(shown)}
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => {
                const typed = event.currentTarget.value;
                onChange(typed === '' ? null : Number(typed));
            }}
        />
    );
}

function numberOrNull(value: JsonValue | undefined): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}
