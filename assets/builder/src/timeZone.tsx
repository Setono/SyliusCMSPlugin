import { createContext, useContext } from 'react';

const TimeZoneContext = createContext<string | null>(null);

/**
 * Gives the time zone the server gives, PHP's default, such as Europe/Copenhagen, to every date and time below it: the
 * builder's and the page scope editor's.
 */
export const TimeZoneProvider = TimeZoneContext.Provider;

/**
 * The time zone every date and time is entered and shown in, whatever the editor's browser uses: PHP's default, which
 * the admin shows times in. See "Dates and times" in docs/spec/builder.md
 */
export function useTimeZone(): string {
    const timeZone = useContext(TimeZoneContext);
    if (timeZone === null) {
        throw new Error('useTimeZone() needs a <TimeZoneProvider>.');
    }

    return timeZone;
}
