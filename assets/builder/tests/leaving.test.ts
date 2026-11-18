import { describe, expect, it, vi } from 'vitest';
import { createLeaving } from '../src/leaving';
import { block, storeWith } from './fixtures';

describe('leaving', () => {
    it('should leave when nothing is left unsaved, even when no save can go through', async () => {
        // Colleagues' changes couldn't be merged into a working copy without changes of its own
        const store = storeWith([block('a')]);
        store.setState({ saveState: 'stale' });
        const go = vi.fn();
        const leaving = createLeaving(store, { flush: async () => false }, go);

        await leaving.getState().leave();

        expect(go).toHaveBeenCalledOnce();
        expect(leaving.getState().failedSave).toBeNull();
    });

    it('should stay when sending the unsaved changes throws', async () => {
        const store = storeWith([block('a')]);
        store.getState().setFieldValue('a', 'text', 'Hello');
        const go = vi.fn();
        const leaving = createLeaving(store, { flush: () => Promise.reject(new Error('Broken')) }, go);

        await leaving.getState().leave();

        expect(go).not.toHaveBeenCalled();
        expect(leaving.getState().failedSave).toBe('failed');
    });

    it('should keep why it stayed, though the save state moves on, such as when a locked draft loads again', async () => {
        const store = storeWith([block('a')]);
        store.getState().setFieldValue('a', 'text', 'Hello');
        const leaving = createLeaving(
            store,
            {
                flush: async () => {
                    store.setState({ saveState: 'locked' });

                    return false;
                },
            },
            vi.fn(),
        );

        await leaving.getState().leave();
        store.setState({ saveState: 'idle' });

        expect(leaving.getState().failedSave).toBe('locked');

        leaving.getState().stay();

        expect(leaving.getState().failedSave).toBeNull();
    });

    it('should leave once the unsaved changes are sent, even when an edit came in meanwhile', async () => {
        const store = storeWith([block('a')]);
        const go = vi.fn();
        const leaving = createLeaving(
            store,
            {
                flush: async () => {
                    store.getState().setFieldValue('a', 'text', 'Hello');

                    return true;
                },
            },
            go,
        );

        await leaving.getState().leave();

        expect(go).toHaveBeenCalledOnce();
    });

    it('should leave for another page, such as a snippet\'s builder, and leave anyway for that page too', async () => {
        const store = storeWith([block('a')]);
        store.getState().setFieldValue('a', 'text', 'Hello');
        const go = vi.fn();
        const leaving = createLeaving(store, { flush: async () => false }, go);

        await leaving.getState().leave('/admin/cms/entry/footer/builder');

        expect(go).not.toHaveBeenCalled();

        leaving.getState().leaveAnyway();

        expect(go).toHaveBeenCalledExactlyOnceWith('/admin/cms/entry/footer/builder');
    });
});
