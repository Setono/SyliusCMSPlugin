import { describe, expect, it, vi } from 'vitest';
import { ApiError } from '../src/api';
import { createSettingsStore } from '../src/settings';
import type { EntrySettings } from '../src/types';
import { settings } from './fixtures';

function deferred<T>() {
    let resolve: (value: T) => void = () => {};
    let reject: (reason: unknown) => void = () => {};
    const promise = new Promise<T>((settle, fail) => {
        resolve = settle;
        reject = fail;
    });

    return { promise, resolve, reject };
}

describe('the on-save settings', () => {
    it('should show a change at once, send only what changed, and take what the server saved', async () => {
        const answer = deferred<EntrySettings>();
        const send = vi.fn(() => answer.promise);
        const store = createSettingsStore(settings(), send);

        const saving = store.getState().save({ enabled: false });

        expect(store.getState().settings.enabled).toBe(false);
        expect(store.getState().saveState).toBe('saving');
        expect(send).toHaveBeenCalledExactlyOnceWith({ enabled: false });

        answer.resolve(settings({ enabled: false, channels: ['B2B', 'WEB'] }));
        await saving;

        expect(store.getState().settings).toEqual(settings({ enabled: false, channels: ['B2B', 'WEB'] }));
        expect(store.getState().saveState).toBe('saved');
        expect(store.getState().error).toBeNull();
    });

    it('should send one save after the other, so the server applies them in order', async () => {
        const first = deferred<EntrySettings>();
        const second = deferred<EntrySettings>();
        const send = vi.fn().mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
        const store = createSettingsStore(settings(), send);

        const firstSave = store.getState().save({ enabled: false });
        const secondSave = store.getState().save({ channels: [] });

        expect(send).toHaveBeenCalledTimes(1);
        expect(store.getState().settings).toEqual(settings({ enabled: false, channels: [] }));

        first.resolve(settings({ enabled: false }));
        await firstSave;

        expect(send).toHaveBeenCalledTimes(2);
        expect(send).toHaveBeenLastCalledWith({ channels: [] });
        expect(store.getState().settings).toEqual(settings({ enabled: false, channels: [] }));
        expect(store.getState().saveState).toBe('saving');

        second.resolve(settings({ enabled: false, channels: [] }));
        await secondSave;

        expect(store.getState().saveState).toBe('saved');
    });

    it('should go back to what the server has and say why when a save fails', async () => {
        const store = createSettingsStore(
            settings({ showFrom: '2026-12-01T00:00:00Z' }),
            vi.fn(() => Promise.reject(new ApiError('"Show until" must come after "Show from"', 400))),
        );

        await store.getState().save({ showUntil: '2026-11-01T00:00:00Z' });

        expect(store.getState().settings).toEqual(settings({ showFrom: '2026-12-01T00:00:00Z' }));
        expect(store.getState().saveState).toBe('failed');
        expect(store.getState().error).toBe('"Show until" must come after "Show from"');
    });

    it('should not give a reason when the save failed for no reason the server gave', async () => {
        const store = createSettingsStore(settings(), vi.fn(() => Promise.reject(new Error('Offline'))));

        await store.getState().save({ enabled: false });

        expect(store.getState().settings.enabled).toBe(true);
        expect(store.getState().saveState).toBe('failed');
        expect(store.getState().error).toBeNull();
    });

    it('should keep a later save when an earlier one fails, and still say that one failed', async () => {
        const first = deferred<EntrySettings>();
        const send = vi
            .fn()
            .mockReturnValueOnce(first.promise)
            .mockResolvedValueOnce(settings({ channels: [] }))
            .mockResolvedValueOnce(settings({ enabled: false, channels: [] }));
        const store = createSettingsStore(settings(), send);

        const firstSave = store.getState().save({ enabled: false });
        const secondSave = store.getState().save({ channels: [] });
        first.reject(new ApiError('Not now', 400));
        await Promise.all([firstSave, secondSave]);

        expect(store.getState().settings).toEqual(settings({ channels: [] }));
        expect(store.getState().saveState).toBe('failed');
        expect(store.getState().error).toBe('Not now');

        await store.getState().save({ enabled: false });

        expect(store.getState().saveState).toBe('saved');
        expect(store.getState().error).toBeNull();
    });
});
