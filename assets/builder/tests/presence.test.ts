import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPresence } from '../src/presence';
import type { PresenceResponse } from '../src/types';
import { block, storeWith } from './fixtures';

function fakeAutosave() {
    return { pull: vi.fn(async () => true) };
}

describe('presence', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('should say the editor has the entry open at once and every five seconds, and keep who else does', async () => {
        const send = vi
            .fn<() => Promise<PresenceResponse>>()
            .mockResolvedValueOnce({ editors: [{ name: 'Jane Roe' }, { name: 'Max Mustermann' }], lockToken: 0 })
            .mockResolvedValue({ editors: [{ name: 'Max Mustermann' }], lockToken: 0 });
        const presence = createPresence(storeWith([block('a')]), fakeAutosave(), send, [{ name: 'Jane Roe' }]);

        expect(presence.store.getState().editors).toEqual([{ name: 'Jane Roe' }]);
        await vi.advanceTimersByTimeAsync(0);

        expect(send).toHaveBeenCalledTimes(1);
        expect(presence.store.getState().editors).toEqual([{ name: 'Jane Roe' }, { name: 'Max Mustermann' }]);

        await vi.advanceTimersByTimeAsync(4999);
        expect(send).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);

        expect(send).toHaveBeenCalledTimes(2);
        expect(presence.store.getState().editors).toEqual([{ name: 'Max Mustermann' }]);
        presence.dispose();
    });

    it('should pull in what colleagues saved once the working copy has moved on', async () => {
        const store = storeWith([block('a')]);
        store.setState({ lockToken: 2 });
        const autosave = fakeAutosave();
        const send = vi
            .fn<() => Promise<PresenceResponse>>()
            .mockResolvedValueOnce({ editors: [], lockToken: 2 })
            .mockResolvedValue({ editors: [{ name: 'Jane Roe' }], lockToken: 3 });
        const presence = createPresence(store, autosave, send, []);

        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.pull).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(5000);
        expect(autosave.pull).toHaveBeenCalledWith(3);
        presence.dispose();
    });

    it('should keep going when the server cannot be reached', async () => {
        const send = vi.fn<() => Promise<PresenceResponse>>().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue({ editors: [], lockToken: 0 });
        const presence = createPresence(storeWith(), fakeAutosave(), send, [{ name: 'Jane Roe' }]);

        await vi.advanceTimersByTimeAsync(0);
        expect(presence.store.getState().editors).toEqual([{ name: 'Jane Roe' }]);

        await vi.advanceTimersByTimeAsync(5000);
        expect(presence.store.getState().editors).toEqual([]);
        presence.dispose();
    });

    it('should stop once disposed', async () => {
        const send = vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 }));
        const presence = createPresence(storeWith(), fakeAutosave(), send, []);
        await vi.advanceTimersByTimeAsync(0);

        presence.dispose();
        await vi.advanceTimersByTimeAsync(20000);

        expect(send).toHaveBeenCalledTimes(1);
    });
});
