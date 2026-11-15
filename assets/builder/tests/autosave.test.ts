import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAutosave } from '../src/autosave';
import type { Change } from '../src/changeSet';
import { ApiError } from '../src/api';
import { createDiscard, createPublish } from '../src/publishing';
import type { Clash, EntryResponse, EntryStatus, SaveResponse } from '../src/types';
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

/** The answer to a save that no colleague's save came before. */
function saved(status: EntryStatus, lockToken = 1): SaveResponse {
    return { status, lockToken };
}

describe('autosave', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('should send one change set a moment after the last edit', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn(async (): Promise<SaveResponse> => saved('never_published'));
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
        expect(send).toHaveBeenCalledWith([hello], 0);
        expect(store.getState().saveState).toBe('saved');
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should never have two saves in flight and send changes made during a save in the next one', async () => {
        const store = storeWith([block('a')]);
        const first = deferred<SaveResponse>();
        const send = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue(saved('never_published', 2));
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);
        store.getState().setFieldValue('a', 'level', 'h3');
        await vi.advanceTimersByTimeAsync(1000);

        expect(send).toHaveBeenCalledTimes(1);

        first.resolve(saved('never_published'));
        await vi.advanceTimersByTimeAsync(0);

        expect(send).toHaveBeenCalledTimes(2);
        expect(send).toHaveBeenLastCalledWith([{ op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' }], 1);
        expect(store.getState().saveState).toBe('saved');
    });

    it('should keep the changes of a failed save for the next one', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue(saved('never_published'));
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);

        expect(store.getState().saveState).toBe('failed');
        expect(store.getState().unsaved).toEqual([hello]);

        store.getState().setFieldValue('a', 'level', 'h3');
        expect(store.getState().saveState).toBe('saving');
        await vi.advanceTimersByTimeAsync(500);

        expect(send).toHaveBeenLastCalledWith([hello, { op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' }], 0);
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

    it('should carry the lock token and keep the one each answer gives', async () => {
        const store = storeWith([block('a')]);
        const send = vi.fn().mockResolvedValueOnce(saved('never_published', 3)).mockResolvedValue(saved('never_published', 4));
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);

        expect(send).toHaveBeenLastCalledWith([hello], 0);
        expect(store.getState().lockToken).toBe(3);

        store.getState().setFieldValue('a', 'level', 'h3');
        await vi.advanceTimersByTimeAsync(500);

        expect(send).toHaveBeenLastCalledWith([{ op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' }], 3);
        expect(store.getState().lockToken).toBe(4);
    });

    it("should pull in the colleagues' changes and the clashes an answer carries", async () => {
        const store = storeWith([block('a'), block('b')]);
        const theirs = entry([block('a', { text: { en_US: 'Hello' } }), block('b', { text: { en_US: 'Theirs' } })]);
        const clash: Clash = { kind: 'value', key: 'b', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' };
        createAutosave(store, async () => ({ status: 'never_published', lockToken: 2, entry: theirs, clashes: [clash] }));

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);

        expect(store.getState().entry).toEqual(theirs);
        expect(store.getState().clashes).toEqual([clash]);
        expect(store.getState().lockToken).toBe(2);
        expect(store.getState().saveState).toBe('saved');
    });

    it('should hold an edit made during a save to a field a colleague changed as a clash, instead of saving it over theirs', async () => {
        const store = storeWith([block('a'), block('b', { text: { en_US: 'World' } }), block('c', { text: { en_US: 'Mine too' } })]);
        const answer = deferred<SaveResponse>();
        const send = vi.fn((): Promise<SaveResponse> => answer.promise);
        createAutosave(store, send);

        store.getState().setFieldValue('a', 'text', 'Hello');
        await vi.advanceTimersByTimeAsync(500);
        // Made while the save is in flight: one to the field the colleague changed, and one to a field they left alone
        store.getState().setFieldValue('b', 'text', 'Mine');
        store.getState().setFieldValue('c', 'text', 'Still mine');
        answer.resolve({
            status: 'never_published',
            lockToken: 2,
            entry: entry([block('a', { text: { en_US: 'Hello' } }), block('b', { text: { en_US: 'Theirs' } }), block('c', { text: { en_US: 'Mine too' } })]),
            clashes: [],
        });
        await vi.advanceTimersByTimeAsync(0);

        expect(store.getState().clashes).toEqual([{ kind: 'value', key: 'b', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' }]);
        expect(store.getState().entry.tree.blocks[1]!.fields.text).toEqual({ en_US: 'Theirs' });
        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'c', field: 'text', locale: 'en_US', value: 'Still mine' }]);
    });

    it('should pull in what colleagues saved once the working copy has moved on, sending nothing new', async () => {
        const store = storeWith([block('a')]);
        const theirs = entry([block('a', { level: 'h3' })]);
        const send = vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1, entry: theirs, clashes: [] }));
        const autosave = createAutosave(store, send);

        await autosave.pull(1);

        expect(send).toHaveBeenCalledWith([], 0);
        expect(store.getState().entry).toEqual(theirs);
        expect(store.getState().lockToken).toBe(1);
        expect(store.getState().saveState).toBe('idle');

        // It has what the server has
        await autosave.pull(1);

        expect(send).toHaveBeenCalledTimes(1);
    });

    it('should leave the save state alone when a pull fails', async () => {
        const store = storeWith([block('a')]);
        const autosave = createAutosave(store, () => Promise.reject(new Error('Offline')));

        expect(await autosave.pull(1)).toBe(false);

        expect(store.getState().saveState).toBe('idle');
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

            return saved('never_published');
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
        const save = vi.fn(async (): Promise<SaveResponse> => saved('unpublished_changes'));
        const autosave = createAutosave(store, save);
        const send = vi.fn(async (): Promise<EntryResponse> => ({ entry: published, lockToken: 5, settings: settings(), status: 'published', editors: [] }));
        const discard = createDiscard(store, autosave, send);

        store.getState().setFieldValue('a', 'text', 'Goodbye');
        const discarding = discard();

        expect(store.getState().discardState).toBe('discarding');
        await discarding;

        expect(save).not.toHaveBeenCalled();
        expect(send).toHaveBeenCalledTimes(1);
        expect(store.getState().entry).toEqual(published);
        expect(store.getState().status).toBe('published');
        expect(store.getState().lockToken).toBe(5);
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().saveState).toBe('idle');
        expect(store.getState().discardState).toBe('idle');
        autosave.dispose();
    });

    it('should wait for a save in flight before it discards', async () => {
        const store = storeWith([block('a')]);
        const saving = deferred<SaveResponse>();
        const calls: string[] = [];
        const autosave = createAutosave(store, () => {
            calls.push('save');

            return saving.promise;
        });
        const discard = createDiscard(store, autosave, async () => {
            calls.push('discard');

            return { entry: published, lockToken: 5, settings: settings(), status: 'published', editors: [] };
        });

        store.getState().setFieldValue('a', 'text', 'Hello world');
        void autosave.flush();
        const discarding = discard();
        await Promise.resolve();

        expect(calls).toEqual(['save']);

        saving.resolve(saved('unpublished_changes'));
        await discarding;

        expect(calls).toEqual(['save', 'discard']);
        expect(store.getState().status).toBe('published');
        autosave.dispose();
    });

    it('should keep the working copy and save its unsaved changes when the discard fails', async () => {
        const store = storeWith([block('a')]);
        const save = vi.fn(async (): Promise<SaveResponse> => saved('unpublished_changes'));
        const autosave = createAutosave(store, save);
        const discard = createDiscard(store, autosave, () => Promise.reject(new Error('Offline')));

        store.getState().setFieldValue('a', 'text', 'Hello world');
        await discard();

        expect(store.getState().discardState).toBe('failed');
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello world' } });
        await vi.waitFor(() => expect(save).toHaveBeenCalledWith([hello2], 0));
        autosave.dispose();
    });

    it('should not discard twice at once', async () => {
        const store = storeWith();
        const autosave = createAutosave(store, vi.fn());
        const send = vi.fn(async (): Promise<EntryResponse> => ({ entry: published, lockToken: 5, settings: settings(), status: 'published', editors: [] }));
        const discard = createDiscard(store, autosave, send);

        await Promise.all([discard(), discard()]);

        expect(send).toHaveBeenCalledTimes(1);
        autosave.dispose();
    });
});
