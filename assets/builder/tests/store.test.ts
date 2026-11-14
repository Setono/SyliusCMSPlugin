import { applyPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { block, keys, storeWith } from './fixtures';

describe('the working copy', () => {
    it('should append a new block of the block type to the top level and select it', () => {
        const store = storeWith([block('a')]);

        const key = store.getState().addBlock('heading');

        expect(key).toBe('new-1');
        expect(keys(store)).toEqual(['a', 'new-1']);
        expect(store.getState().entry.tree.blocks[1]).toEqual({
            key: 'new-1',
            type: 'heading',
            version: 1,
            style: null,
            fields: {},
            translated: {},
            conditions: [],
            children: [],
        });
        expect(store.getState().selectedKey).toBe('new-1');
    });

    it('should not add a block of a block type the registry does not have', () => {
        const store = storeWith();

        expect(store.getState().addBlock('carousel')).toBeNull();
        expect(keys(store)).toEqual([]);
        expect(store.getState().history).toHaveLength(0);
    });

    it('should remove a block and deselect it when it was selected', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);
        store.getState().selectBlock('b');

        store.getState().removeBlock('b');

        expect(keys(store)).toEqual(['a', 'c']);
        expect(store.getState().selectedKey).toBeNull();
    });

    it('should keep the selection when another block is removed', () => {
        const store = storeWith([block('a'), block('b')]);
        store.getState().selectBlock('a');

        store.getState().removeBlock('b');

        expect(store.getState().selectedKey).toBe('a');
    });

    it('should keep each edit with the inverse patches that undo it', () => {
        const store = storeWith();
        const empty = store.getState().entry;

        store.getState().addBlock('heading');
        const added = store.getState().entry;
        store.getState().setFieldValue('new-1', 'text', 'Hello');

        const { history, entry: typed } = store.getState();
        expect(history).toHaveLength(2);
        expect(history[0]?.patches).toEqual([{ op: 'add', path: ['tree', 'blocks', 0], value: expect.objectContaining({ key: 'new-1' }) }]);
        expect(applyPatches(typed, history[1]?.inversePatches ?? [])).toEqual(added);
        expect(applyPatches(added, history[0]?.inversePatches ?? [])).toEqual(empty);
        expect(store.getState().revision).toBe(2);
    });

    it('should store a translatable value in the edited locale and a shared value as is', () => {
        const store = storeWith([block('a', { text: { da_DK: 'Hej' } })]);

        store.getState().setFieldValue('a', 'text', 'Hello');
        store.getState().setFieldValue('a', 'level', 'h3');

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { da_DK: 'Hej', en_US: 'Hello' }, level: 'h3' });
    });

    it('should leave out a cleared value', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' }, level: 'h3' })]);

        store.getState().setFieldValue('a', 'text', '');
        store.getState().setFieldValue('a', 'level', null);

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({});
    });

    it('should not record an edit that changes nothing', () => {
        const store = storeWith([block('a', { level: 'h3' })]);

        store.getState().setFieldValue('a', 'level', 'h3');
        store.getState().removeBlock('missing');

        expect(store.getState().history).toHaveLength(0);
        expect(store.getState().revision).toBe(0);
    });
});
