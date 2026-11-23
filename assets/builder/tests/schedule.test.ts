import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../src/api';
import { createAutosave } from '../src/autosave';
import { formatDateTime, fromLocalInput, toLocalInput } from '../src/dates';
import { createCancelSchedule, createPublish, createReload, createSchedule } from '../src/publishing';
import { locks } from '../src/store';
import type { EntryResponse, Schedule } from '../src/types';
import { createValidationStore } from '../src/validation';
import { block, entry, keys, settings, storeWith } from './fixtures';

const waiting: Schedule = { at: '2026-11-27T08:00:00Z', by: 'Jane Roe', status: 'publishes_at_a_time', reason: null };

const overdue: Schedule = { ...waiting, status: 'scheduled_publish_overdue' };

const failed: Schedule = { ...waiting, status: 'scheduled_publish_failed', reason: 'The campaign was postponed' };

/** The answer that loads the working copy with this scheduled publish. */
function answer(schedule: Schedule | null, text = 'Hello'): EntryResponse {
    return { entry: entry([block('a', { text: { en_US: text } })]), settings: settings(), status: 'unpublished_changes', lockToken: 4, schedule, editors: [] };
}

describe('the lock of a scheduled publish', () => {
    it('should lock the working copy while it waits, overdue or not, and not once it failed', () => {
        expect(locks(null)).toBe(false);
        expect(locks(waiting)).toBe(true);
        expect(locks(overdue)).toBe(true);
        expect(locks(failed)).toBe(false);
    });

    it('should refuse every edit while the working copy is locked', () => {
        const store = storeWith([block('a'), block('b')]);
        store.setState({ schedule: waiting });

        expect(store.getState().addBlock('heading')).toBeNull();
        expect(store.getState().moveBlock('b', null, 0)).toBe(false);
        expect(store.getState().duplicateBlocks(['a'])).toBeNull();
        store.getState().removeBlocks(['a']);
        store.getState().setFieldValue('a', 'text', 'Hello');
        store.getState().setEntryValue('title', 'About us');

        expect(keys(store)).toEqual(['a', 'b']);
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({});
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().changeCount).toBe(0);
    });

    it('should leave undo and redo nothing to do while the working copy is locked', () => {
        const store = storeWith([block('a')]);
        store.getState().setFieldValue('a', 'text', 'Hello');
        store.setState({ schedule: waiting, unsaved: [] });

        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello' } });
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should take edits again once a failed scheduled publish unlocked the working copy', () => {
        const store = storeWith([block('a')]);
        store.setState({ schedule: failed });

        store.getState().setFieldValue('a', 'text', 'Hello');

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello' } });
    });

    it('should put the scheduled publish in place with a loaded working copy, and keep it when a load has none', () => {
        const store = storeWith([block('a')]);

        store.getState().load(entry([block('b')]), 'unpublished_changes', 3, waiting);
        expect(store.getState().schedule).toEqual(waiting);

        store.getState().load(entry([block('c')]), 'unpublished_changes', 4);
        expect(store.getState().schedule).toEqual(waiting);

        store.getState().load(entry([block('c')]), 'unpublished_changes', 5, null);
        expect(store.getState().schedule).toBeNull();
    });
});

describe('autosave and the lock', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('should stop saving when the server says a scheduled publish locks the working copy', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn().mockRejectedValue(new ApiError('A publish scheduled for 2026-11-27T08:00:00+00:00 locks the draft', 423));
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);

        expect(store.getState().saveState).toBe('locked');

        store.getState().setFieldValue('a', 'level', 'h3');
        await vi.advanceTimersByTimeAsync(500);

        expect(send).toHaveBeenCalledTimes(1);
        expect(store.getState().saveState).toBe('locked');
    });

    it('should load the working copy again with its scheduled publish, read-only', async () => {
        const store = storeWith([block('a')]);
        store.getState().setFieldValue('a', 'text', 'Mine');
        store.setState({ saveState: 'locked' });

        await createReload(store, async () => answer(waiting))();

        expect(store.getState().schedule).toEqual(waiting);
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().saveState).toBe('idle');
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello' } });
    });
});

describe('scheduling a publish', () => {
    it('should save what is unsaved, schedule it, and load the working copy it locks', async () => {
        const store = storeWith([block('a')]);
        const calls: string[] = [];
        const autosave = { flush: vi.fn(async () => (calls.push('flush'), true)), pull: vi.fn(), dispose: vi.fn() };
        const send = vi.fn(async (at: string) => (calls.push(`schedule ${at}`), answer(waiting)));

        expect(await createSchedule(store, autosave, send)('2026-11-27T08:00:00Z')).toBe(true);

        expect(calls).toEqual(['flush', 'schedule 2026-11-27T08:00:00Z']);
        expect(store.getState().schedule).toEqual(waiting);
        expect(store.getState().status).toBe('unpublished_changes');
        expect(store.getState().lockToken).toBe(4);
        expect(store.getState().scheduleState).toBe('idle');
    });

    it('should say why the server refused, and show the errors validation found', async () => {
        const store = storeWith([block('a'), block('b')]);
        const validation = createValidationStore();
        const autosave = { flush: vi.fn(async () => true), pull: vi.fn(), dispose: vi.fn() };
        const error = { block: 'b', field: 'text', locale: 'en_US', message: 'This field is required.' };
        const send = vi.fn(() => Promise.reject(new ApiError('One error needs fixing', 422, [error])));

        expect(await createSchedule(store, autosave, send, validation)('2026-11-27T08:00:00Z')).toBe(false);

        expect(store.getState().scheduleState).toBe('failed');
        expect(store.getState().scheduleRefusal).toBe('One error needs fixing');
        expect(store.getState().schedule).toBeNull();
        expect(validation.getState().errors).toEqual([error]);
        expect(store.getState().selectedKey).toBe('b');
    });

    it('should not schedule what it could not save first', async () => {
        const store = storeWith([block('a')]);
        const autosave = { flush: vi.fn(async () => false), pull: vi.fn(), dispose: vi.fn() };
        const send = vi.fn(async () => answer(waiting));

        expect(await createSchedule(store, autosave, send)('2026-11-27T08:00:00Z')).toBe(false);

        expect(send).not.toHaveBeenCalled();
        expect(store.getState().scheduleState).toBe('failed');
        expect(store.getState().scheduleRefusal).toBeNull();
    });

    it('should not schedule twice at once', async () => {
        const store = storeWith([block('a')]);
        store.setState({ scheduleState: 'scheduling' });
        const send = vi.fn(async () => answer(waiting));

        expect(await createSchedule(store, { flush: vi.fn(async () => true), pull: vi.fn(), dispose: vi.fn() }, send)('2026-11-27T08:00:00Z')).toBe(false);

        expect(send).not.toHaveBeenCalled();
    });

    it('should cancel it, and load the working copy unlocked', async () => {
        const store = storeWith([block('a')]);
        store.setState({ schedule: waiting, saveState: 'locked' });

        await createCancelSchedule(store, async () => answer(null, 'Hello there'))();

        expect(store.getState().schedule).toBeNull();
        expect(store.getState().cancelScheduleState).toBe('idle');
        expect(store.getState().saveState).toBe('idle');
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello there' } });
    });

    it('should say so when it could not cancel, and stay locked', async () => {
        const store = storeWith([block('a')]);
        store.setState({ schedule: waiting });

        await createCancelSchedule(store, () => Promise.reject(new Error('Offline')))();

        expect(store.getState().cancelScheduleState).toBe('failed');
        expect(store.getState().schedule).toEqual(waiting);
    });

    it('should end the scheduled publish with a publish now', async () => {
        const store = storeWith([block('a')]);
        store.setState({ schedule: failed });

        await createPublish(store, { flush: vi.fn(async () => true), pull: vi.fn(), dispose: vi.fn() }, async () => 'published')();

        expect(store.getState().schedule).toBeNull();
    });
});

describe('the time of a scheduled publish', () => {
    // The editor's browser is in another time zone than the server's
    beforeEach(() => {
        vi.stubEnv('TZ', 'Asia/Tokyo');
    });

    afterEach(() => {
        vi.unstubAllEnvs();
    });

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

    it('should use the browser’s own time zone without one', () => {
        expect(fromLocalInput('2026-11-27T09:00')).toBe('2026-11-27T00:00:00Z');
        expect(formatDateTime('2026-11-27T00:00:00Z')).toBe('2026-11-27 09:00');
    });
});
