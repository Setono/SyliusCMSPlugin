import styles from './Form.module.css';
import type { FieldControlProps } from './types';
import { UnsupportedControl } from './UnsupportedControl';
import { controlFor } from './controls';

export type FieldRowProps = Omit<FieldControlProps, 'describedBy'> & {
    /** What publish would refuse in the field, shown under it and describing its control. */
    errors?: readonly string[];
};

/** One field rendered from its schema: its label, its field type's control, its help text and its errors. */
export function FieldRow({ errors = [], ...props }: FieldRowProps) {
    const { id, schema } = props;
    const Control = controlFor(schema['x-cms-type']);
    const label = schema['x-cms-label'];
    const help = schema['x-cms-help'];
    const helpId = help ? `${id}-help` : undefined;
    const errorsId = errors.length > 0 ? `${id}-errors` : undefined;
    const describedBy = [helpId, errorsId].filter((describing) => describing !== undefined).join(' ');

    return (
        <div className={styles.field}>
            <div className={styles.labelRow}>
                {Control ? (
                    <label htmlFor={id} className={styles.label}>
                        {label}
                    </label>
                ) : (
                    <span className={styles.label}>{label}</span>
                )}
                {schema['x-cms-required'] && (
                    <span className={styles.required} aria-hidden="true">
                        *
                    </span>
                )}
            </div>
            {Control ? <Control {...props} describedBy={describedBy === '' ? undefined : describedBy} /> : <UnsupportedControl id={id} />}
            {help ? (
                <p id={helpId} className={styles.help}>
                    {help}
                </p>
            ) : null}
            {errorsId === undefined ? null : (
                <ul id={errorsId} className={styles.errors}>
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
