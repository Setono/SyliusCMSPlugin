import type { JsonValue } from '../types';
import { isJsonObject } from '../values';
import styles from './Form.module.css';
import type { FieldWidgetProps } from './types';

export function ChoiceWidget({ id, value, onChange, schema, describedBy }: FieldWidgetProps) {
    const options = schema['x-cms-options'];
    const choices = Object.entries(isJsonObject(options?.choices) ? options.choices : {});
    const fallback = schema.default;
    const common = {
        id,
        className: styles.select,
        'aria-required': schema['x-cms-required'] || undefined,
        'aria-describedby': describedBy,
    };
    const optionList = choices.map(([choice, label]) => (
        <option key={choice} value={choice}>
            {String(label)}
        </option>
    ));

    if (options?.multiple === true) {
        return (
            <select
                {...common}
                multiple
                value={strings(value ?? fallback)}
                onChange={(event) => onChange(Array.from(event.currentTarget.selectedOptions, (option) => option.value))}
            >
                {optionList}
            </select>
        );
    }

    const selected = typeof value === 'string' ? value : typeof fallback === 'string' ? fallback : '';
    // Without a default, an empty choice shows that nothing is chosen, and lets an optional field be cleared.
    const allowEmpty = fallback === undefined && (selected === '' || !schema['x-cms-required']);

    return (
        <select {...common} value={selected} onChange={(event) => onChange(event.currentTarget.value)}>
            {allowEmpty && <option value="" />}
            {optionList}
        </select>
    );
}

function strings(value: JsonValue | undefined): string[] {
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
}
