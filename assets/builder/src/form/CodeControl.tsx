import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/** Code, such as an embed's HTML and scripts, kept exactly as it's typed: monospace, left to right, and never spell checked or capitalised. */
export function CodeControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    return (
        <textarea
            id={id}
            className={`${styles.textarea} ${styles.code}`}
            rows={8}
            value={typeof value === 'string' ? value : ''}
            dir="ltr"
            spellCheck={false}
            autoCapitalize="off"
            autoComplete="off"
            autoCorrect="off"
            aria-required={schema['x-cms-required'] || undefined}
            aria-describedby={describedBy}
            onChange={(event) => onChange(event.currentTarget.value)}
        />
    );
}
