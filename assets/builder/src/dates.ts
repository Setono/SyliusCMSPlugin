/**
 * Date and time values travel in UTC, such as 2026-11-27T00:00:00Z, while editors enter them in a datetime-local input,
 * such as 2026-11-27T01:00, in a time zone: the editor's own, or the one the server gives, such as Europe/Copenhagen,
 * which scheduled publishes use. See "Scheduled publishes" in docs/spec/publishing.md
 */

/** A datetime-local input's value, whose seconds are left out unless they're set */
const LOCAL = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/;

const HOUR = 60 * 60 * 1000;

/** The value a datetime-local input shows for a UTC date and time, in the time zone or else the editor's own, or '' for none. */
export function toLocalInput(utc: string | null, timeZone?: string): string {
    if (utc === null) {
        return '';
    }

    const { year, month, day, hour, minute } = wallTime(new Date(utc).getTime(), timeZone);
    const pad = (value: number, length = 2): string => String(value).padStart(length, '0');

    return `${pad(year, 4)}-${pad(month)}-${pad(day)}T${pad(hour)}:${pad(minute)}`;
}

/**
 * The UTC date and time of a datetime-local input's value, in the time zone or else the editor's own, or null when it's
 * empty or incomplete. A time the clocks skip is read as that long after the time before the skip, and a time they
 * repeat as the second, as PHP reads them.
 */
export function fromLocalInput(local: string, timeZone?: string): string | null {
    const match = LOCAL.exec(local);
    if (match === null) {
        return null;
    }

    const [year, month, day, hour, minute, second = 0] = match.slice(1).map((part) => Number(part ?? 0)) as [number, number, number, number, number, number?];
    // The time as if the zone were UTC, which is off by the zone's offset at the time
    const asUtc = Date.UTC(year, month - 1, day, hour, minute, second);
    // Not a date and time, such as 2026-02-30T09:00, which Date.UTC() rolls over to another
    if (new Date(asUtc).toISOString().slice(0, 16) !== local.slice(0, 16)) {
        return null;
    }

    // The offsets either side of a change of the clocks, which is never more than one a day
    const before = asUtc - offset(asUtc - 24 * HOUR, timeZone);
    const after = asUtc - offset(asUtc + 24 * HOUR, timeZone);
    const shows = (time: number): boolean => toLocalInput(new Date(time).toISOString(), timeZone) === local.slice(0, 16);
    const time = shows(after) ? after : before;

    return new Date(time).toISOString().replace(/\.\d{3}Z$/, 'Z');
}

/** A UTC date and time as the builder shows it, in the time zone or else the editor's own, such as 2026-11-27 01:00, as the admin shows times. */
export function formatDateTime(utc: string, timeZone?: string): string {
    return toLocalInput(utc, timeZone).replace('T', ' ');
}

interface WallTime {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    second: number;
}

/** What clocks in the time zone, or else the editor's own, show at a time. */
function wallTime(time: number, timeZone: string | undefined): WallTime {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
    }).formatToParts(new Date(time));
    const part = (type: Intl.DateTimeFormatPartTypes): number => Number(parts.find((candidate) => candidate.type === type)?.value);

    return { year: part('year'), month: part('month'), day: part('day'), hour: part('hour'), minute: part('minute'), second: part('second') };
}

/** How far the clocks in the time zone, or else the editor's own, are ahead of UTC at a time, in milliseconds. */
function offset(time: number, timeZone: string | undefined): number {
    const { year, month, day, hour, minute, second } = wallTime(time, timeZone);

    return Date.UTC(year, month - 1, day, hour, minute, second) - Math.floor(time / 1000) * 1000;
}
