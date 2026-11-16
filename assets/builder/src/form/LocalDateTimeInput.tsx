import { type InputHTMLAttributes, useEffect, useState } from 'react';
import { fromLocalInput, toLocalInput } from '../dates';

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value' | 'onChange' | 'onBlur' | 'onKeyDown'> & {
    /** The date and time in UTC, or null for none. */
    value: string | null;
    /** Called with the UTC date and time the editor entered, or null when they emptied the input. */
    onCommit: (value: string | null) => void;
    /**
     * When what's typed is handed on: at each change, once the input holds a whole date and time or nothing, or when the
     * editor leaves the input or presses Enter, and only when it differs from the value.
     */
    commitOn: 'change' | 'blur';
};

/**
 * A date and time stored in UTC, which editors enter in their own time zone, as a datetime-local input. It shows the
 * value again whenever that changes, such as after an undo or a save the server refused. The one date and time input
 * of the builder: a Date and time field, the entry's date window and the preview's date.
 */
export function LocalDateTimeInput({ value, onCommit, commitOn, ...props }: Props) {
    const [local, setLocal] = useState(toLocalInput(value));

    useEffect(() => setLocal(toLocalInput(value)), [value]);

    const commit = (): void => {
        const utc = fromLocalInput(local);
        if (utc !== value) {
            onCommit(utc);
        }
    };

    return (
        <input
            {...props}
            type="datetime-local"
            value={local}
            onChange={(event) => {
                const next = event.currentTarget.value;
                setLocal(next);

                const utc = fromLocalInput(next);
                if (commitOn === 'change' && (next === '' || utc !== null)) {
                    onCommit(utc);
                }
            }}
            onBlur={commitOn === 'blur' ? commit : undefined}
            onKeyDown={
                commitOn === 'blur'
                    ? (event) => {
                          if (event.key === 'Enter') {
                              commit();
                          }
                      }
                    : undefined
            }
        />
    );
}
