import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDateTime, fromLocalInput, toLocalInput } from '../src/dates';
import { LocalDateTimeInput } from '../src/form/LocalDateTimeInput';
import { TimeZoneProvider } from '../src/timeZone';

/** The date and time input, in the time zone the server gives the builder. */
function renderInput(value: string | null, commitOn: 'change' | 'blur' = 'change', onCommit = vi.fn()) {
    render(
        <TimeZoneProvider value="Europe/Copenhagen">
            <LocalDateTimeInput aria-label="At" value={value} commitOn={commitOn} onCommit={onCommit} />
        </TimeZoneProvider>,
    );

    return onCommit;
}

// The editor's browser is in another time zone than the server's
beforeEach(() => {
    vi.stubEnv('TZ', 'Asia/Tokyo');
});

afterEach(() => {
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
});

describe('dates and times in a time zone', () => {
    it('should read a time entered in the time zone the server gives, not the browser’s', () => {
        expect(fromLocalInput('2026-11-27T09:00', 'Europe/Copenhagen')).toBe('2026-11-27T08:00:00Z');
        expect(fromLocalInput('2026-07-01T09:00', 'Europe/Copenhagen')).toBe('2026-07-01T07:00:00Z');
        expect(fromLocalInput('2026-11-27T09:00', 'America/New_York')).toBe('2026-11-27T14:00:00Z');
        expect(fromLocalInput('2026-11-27T09:00', 'UTC')).toBe('2026-11-27T09:00:00Z');
        expect(fromLocalInput('', 'Europe/Copenhagen')).toBeNull();
        expect(fromLocalInput('2026-11-27T', 'Europe/Copenhagen')).toBeNull();
        expect(fromLocalInput('2026-02-30T09:00', 'Europe/Copenhagen')).toBeNull();
        expect(fromLocalInput('2026-11-27T09:00:30', 'Europe/Copenhagen')).toBe('2026-11-27T08:00:30Z');
    });

    it('should read a time the clocks skip or repeat as PHP reads it', () => {
        // 02:30 doesn't exist on the day summer time starts, and is read as 03:30 summer time
        expect(fromLocalInput('2026-03-29T02:30', 'Europe/Copenhagen')).toBe('2026-03-29T01:30:00Z');
        // 02:30 happens twice on the day summer time ends, and is read as the second, in winter time
        expect(fromLocalInput('2026-10-25T02:30', 'Europe/Copenhagen')).toBe('2026-10-25T01:30:00Z');
    });

    it('should show a time in the time zone the server gives, not the browser’s', () => {
        expect(formatDateTime('2026-11-27T08:00:00Z', 'Europe/Copenhagen')).toBe('2026-11-27 09:00');
        expect(formatDateTime('2026-07-01T07:00:00Z', 'Europe/Copenhagen')).toBe('2026-07-01 09:00');
        expect(formatDateTime('2026-11-27T04:30:00Z', 'America/New_York')).toBe('2026-11-26 23:30');
        expect(toLocalInput('2026-11-27T08:00:00Z', 'Europe/Copenhagen')).toBe('2026-11-27T09:00');
        expect(toLocalInput(null, 'Europe/Copenhagen')).toBe('');
    });
});

describe('the date and time input', () => {
    it('should show a UTC date and time in the time zone the server gives, not the browser’s, and name it', () => {
        renderInput('2026-11-27T08:00:00Z');

        const input = screen.getByLabelText('At');
        expect(input).toHaveValue('2026-11-27T09:00');
        expect(input).toHaveAccessibleDescription('Europe/Copenhagen');
        expect(screen.getByText('Europe/Copenhagen')).toBeVisible();
    });

    it('should hand on what the editor enters in UTC, read in the time zone the server gives, not the browser’s', () => {
        const onCommit = renderInput(null);

        fireEvent.change(screen.getByLabelText('At'), { target: { value: '2026-07-01T09:00' } });

        // Copenhagen is two hours ahead of UTC in summer, Tokyo nine
        expect(onCommit).toHaveBeenLastCalledWith('2026-07-01T07:00:00Z');
    });

    it('should hand it on in UTC when the editor leaves the input or presses Enter', () => {
        const onCommit = renderInput('2026-11-27T08:00:00Z', 'blur');
        const input = screen.getByLabelText('At');

        fireEvent.change(input, { target: { value: '2026-11-27T10:00' } });
        expect(onCommit).not.toHaveBeenCalled();
        fireEvent.blur(input);
        expect(onCommit).toHaveBeenLastCalledWith('2026-11-27T09:00:00Z');

        fireEvent.change(input, { target: { value: '2026-11-27T11:00' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(onCommit).toHaveBeenLastCalledWith('2026-11-27T10:00:00Z');
    });

    it('should put the time zone after what else describes it', () => {
        render(
            <TimeZoneProvider value="Europe/Copenhagen">
                <p id="help">When the sale starts</p>
                <LocalDateTimeInput aria-label="At" aria-describedby="help" value={null} commitOn="change" onCommit={vi.fn()} />
            </TimeZoneProvider>,
        );

        expect(screen.getByLabelText('At')).toHaveAccessibleDescription('When the sale starts Europe/Copenhagen');
    });

    it('should need the time zone the server gives', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(() => render(<LocalDateTimeInput aria-label="At" value={null} commitOn="change" onCommit={vi.fn()} />)).toThrow('useTimeZone() needs a <TimeZoneProvider>.');
    });
});
