/**
 * Date and time values travel in UTC, such as 2026-11-27T00:00:00Z, while editors enter them in their own time zone in
 * a datetime-local input, such as 2026-11-27T01:00.
 */

/** The value a datetime-local input shows for a UTC date and time, or '' for none. */
export function toLocalInput(utc: string | null): string {
    if (utc === null) {
        return '';
    }

    const date = new Date(utc);
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The UTC date and time of a datetime-local input's value, or null when it's empty or incomplete. */
export function fromLocalInput(local: string): string | null {
    const date = new Date(local);

    return local === '' || Number.isNaN(date.getTime()) ? null : date.toISOString().replace(/\.\d{3}Z$/, 'Z');
}
