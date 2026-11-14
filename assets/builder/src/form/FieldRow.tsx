import styles from './Form.module.css';
import type { FieldWidgetProps } from './types';
import { UnsupportedWidget } from './UnsupportedWidget';
import { widgetFor } from './widgets';

export type FieldRowProps = Omit<FieldWidgetProps, 'describedBy'>;

/** One field rendered from its schema: its label, its field type's control and its help text. */
export function FieldRow(props: FieldRowProps) {
    const { id, schema } = props;
    const Widget = widgetFor(schema['x-cms-type']);
    const label = schema['x-cms-label'];
    const help = schema['x-cms-help'];
    const helpId = help ? `${id}-help` : undefined;

    return (
        <div className={styles.field}>
            <div className={styles.labelRow}>
                {Widget ? (
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
            {Widget ? <Widget {...props} describedBy={helpId} /> : <UnsupportedWidget id={id} />}
            {help ? (
                <p id={helpId} className={styles.help}>
                    {help}
                </p>
            ) : null}
        </div>
    );
}
