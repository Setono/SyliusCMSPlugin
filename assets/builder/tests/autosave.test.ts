import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAutosave } from '../src/autosave';
import type { Change } from '../src/changeSet';
import { ApiError } from '../src/api';
import { createDiscard, createPublish } from '../src/publishing';
import type { EntryResponse, EntryStatus } from '../src/types';
import { block, entry, settings, storeWith } from './fixtures';

/** A promise to settle from the test. */
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

const hello: Change = { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' };

const hello2: Change = { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello world' };

describe('autosave', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('should send one change set a moment after the last edit', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn(async (): Promise<EntryStatus> => 'never_published');
        createAutosave(store, send);

        expect(store.getState().saveState).toBe('idle');

        store.getState().setFieldValue('a', 'text', 'Hel');
        expect(store.getState().saveState).toBe('saving');
        await vi.advanceTimersByTimeAsync(300);
        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(499);

        expect(send).not.toHaveBeenCalled();
        expect(store.getState().saveState).toBe('saving');

        await vi.advanceTimersByTimeAsync(1);

        expect(send).toHaveBeenCalledTimes(1);
        expect(send).toHaveBeenCalledWith([hello]);
        expect(store.getState().saveState).toBe('saved');
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should never have two saves in flight and send changes made during a save in the next one', async () => {
        const store = storeWith([block('a')]);
        const first = deferred<EntryStatus>();
        const send = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue('never_published');
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);
        store.getState().setFieldValue('a', 'level', 'h3');
        await vi.advanceTimersByTimeAsync(1000);

        expect(send).toHaveBeenCalledTimes(1);

        first.resolve('never_published');
        await vi.advanceTimersByTimeAsync(0);

        expect(send).toHaveBeenCalledTimes(2);
        expect(send).toHaveBeenLastCalledWith([{ op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' }]);
        expect(store.getState().saveState).toBe('saved');
    });

    it('should keep the changes of a failed save for the next one', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue('never_published');
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);

        expect(store.getState().saveState).toBe('failed');
        expect(store.getState().unsaved).toEqual([hello]);

        store.getState().setFieldValue('a', 'level', 'h3');
        expect(store.getState().saveState).toBe('saving');
        await vi.advanceTimersByTimeAsync(500);

        expect(send).toHaveBeenLastCalledWith([hello, { op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' }]);
        expect(store.getState().saveState).toBe('saved');
    });

    it('should show saved when an edit cancels out the unsaved changes', () => {
        const store = storeWith();
        const send = vi.fn();
        createAutosave(store, send);

        store.getState().addBlock('heading');
        store.getState().removeBlock('new-1');

        expect(store.getState().saveState).toBe('saved');
        vi.runAllTimers();
        expect(send).not.toHaveBeenCalled();
    });

    it('should stop saving once disposed', () => {
        const store = storeWith([block('a')]);
        const send = vi.fn();
        const autosave = createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        autosave.dispose();
        vi.runAllTimers();

        expect(send).not.toHaveBeenCalled();
    });
});

describe('publishing', () => {
    it('should save the unsaved changes before it publishes', async () => {
        const store = storeWith([block('a')]);
        const calls: string[] = [];
        const autosave = createAutosave(store, async () => {
            calls.push('save');

            return 'never_published';
        });
        const publish = createPublish(store, autosave, async () => {
            calls.push('publish');

            return 'published';
        });

        store.getState().setFieldValue('a', 'text', 'Hello');
        const publishing = publish();

        expect(store.getState().publishState).toBe('publishing');
        await publishing;

        expect(calls).toEqual(['save', 'publish']);
        expect(store.getState().status).toBe('published');
        expect(store.getState().publishState).toBe('idle');
        expect(store.getState().saveState).toBe('saved');
        autosave.dispose();
    });

    it('should not publish when the unsaved changes cannot be saved', async () => {
        const store = storeWith([block('a')]);
        const autosave = createAutosave(store, () => Promise.reject(new Error('Offline')));
        const send = vi.fn();
        const publish = createPublish(store, autosave, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await publish();

        expect(send).not.toHaveBeenCalled();
        expect(store.getState().publishState).toBe('failed');
        expect(store.getState().status).toBe('never_published');
        autosave.dispose();
    });

    it('should report a failed publish', async () => {
        const store = storeWith();
        const autosave = createAutosave(store, vi.fn());
        const publish = createPublish(store, autosave, () => Promise.reject(new Error('Invalid')));

        await publish();

        expect(store.getState().publishState).toBe('failed');
        expect(store.getState().publishRefusal).toBeNull();
        autosave.dispose();
    });

    it('should keep the reason the server refused a publish for, until a publish goes through', async () => {
        const store = storeWith();
        const autosave = createAutosave(store, vi.fn());
        const send = vi.fn().mockRejectedValueOnce(new ApiError('Not on a Sunday', 422)).mockResolvedValue('published');
        const publish = createPublish(store, autosave, send);

        await publish();

        expect(store.getState().publishState).toBe('failed');
        expect(store.getState().publishRefusal).toBe('Not on a Sunday');

        await publish();

        expect(store.getState().publishState).toBe('idle');
        expect(store.getState().publishRefusal).toBeNull();
        autosave.dispose();
    });
});

describe('discarding changes', () => {
    const published = entry([block('a', { text: { en_US: 'Hello' } })]);

    it('should drop the unsaved changes, discard the draft and load the published content', async () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello world' } })]);
        store.setState({ status: 'unpublished_changes' });
        const save = vi.fn(async (): Promise<EntryStatus> => 'unpublished_changes');
        const autosave = createAutosave(store, save);
        const send = vi.fn(async (): Promise<EntryResponse> => ({ entry: published, settings: settings(), status: 'published' }));
        const discard = createDiscard(store, autosave, send);

        store.getState().setFieldValue('a', 'text', 'Goodbye');
        const discarding = discard();

        expect(store.getState().discardState).toBe('discarding');
        await discarding;

        expect(save).not.toHaveBeenCalled();
        expect(send).toHaveBeenCalledTimes(1);
        expect(store.getState().entry).toEqual(published);
        expect(store.getState().status).toBe('published');
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().saveState).toBe('idle');
        expect(store.getState().discardState).toBe('idle');
        autosave.dispose();
    });

    it('should wait for a save in flight before it discards', async () => {
        const store = storeWith([block('a')]);
        const saving = deferred<EntryStatus>();
        const calls: string[] = [];
        const autosave = createAutosave(store, () => {
            calls.push('save');

            return saving.promise;
        });
        const discard = createDiscard(store, autosave, async () => {
            calls.push('discard');

            return { entry: published, settings: settings(), status: 'published' };
        });

        store.getState().setFieldValue('a', 'text', 'Hello world');
        void autosave.flush();
        const discarding = discard();
        await Promise.resolve();

        expect(calls).toEqual(['save']);

        saving.resolve('unpublished_changes');
        await discarding;

        expect(calls).toEqual(['save', 'discard']);
        expect(store.getState().status).toBe('published');
        autosave.dispose();
    });

    it('should keep the working copy and save its unsaved changes when the discard fails', async () => {
        const store = storeWith([block('a')]);
        const save = vi.fn(async (): Promise<EntryStatus> => 'unpublished_changes');
        const autosave = createAutosave(store, save);
        const discard = createDiscard(store, autosave, () => Promise.reject(new Error('Offline')));

        store.getState().setFieldValue('a', 'text', 'Hello world');
        await discard();

        expect(store.getState().discardState).toBe('failed');
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello world' } });
        await vi.waitFor(() => expect(save).toHaveBeenCalledWith([hello2]));
        autosave.dispose();
    });

    it('should not discard twice at once', async () => {
        const store = storeWith();
        const autosave = createAutosave(store, vi.fn());
        const send = vi.fn(async (): Promise<EntryResponse> => ({ entry: published, settings: settings(), status: 'published' }));
        const discard = createDiscard(store, autosave, send);

        await Promise.all([discard(), discard()]);

        expect(send).toHaveBeenCalledTimes(1);
        autosave.dispose();
    });
});
