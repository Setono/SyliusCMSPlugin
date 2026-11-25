import { type InputHTMLAttributes, useEffect, useId, useState } from 'react';
import { fromLocalInput, toLocalInput } from '../dates';
import { useTimeZone } from '../timeZone';
import styles from './LocalDateTimeInput.module.css';

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
 * A date and time stored in UTC, which editors enter in the time zone the server gives, whatever their own, as a
 * datetime-local input labelled with the zone's name. It shows the value again whenever that changes, such as after an
 * undo or a save the server refused. The one date and time input of the builder: a Date and time field, the entry's
 * date window and the preview's date. See "Dates and times" in docs/spec/builder.md
 */
export function LocalDateTimeInput({ value, onCommit, commitOn, 'aria-describedby': describedBy, ...props }: Props) {
    const timeZone = useTimeZone();
    const timeZoneId = useId();
    const [local, setLocal] = useState(toLocalInput(value, timeZone));

    useEffect(() => setLocal(toLocalInput(value, timeZone)), [value, timeZone]);

    const commit = (): void => {
        const utc = fromLocalInput(local, timeZone);
        if (utc !== value) {
            onCommit(utc);
        }
    };

    return (
        <span className={styles.dateTime}>
            <input
                {...props}
                type="datetime-local"
                value={local}
                // What describes it, such as its help and its errors, then its time zone
                aria-describedby={describedBy === undefined ? timeZoneId : `${describedBy} ${timeZoneId}`}
                onChange={(event) => {
                    const next = event.currentTarget.value;
                    setLocal(next);

                    const utc = fromLocalInput(next, timeZone);
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
            <span id={timeZoneId} className={styles.timeZone}>
                {timeZone}
            </span>
        </span>
    );
}
