import { applyPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { block, blockOf, blockTypes, keys, shape, spacer, storeWith } from './fixtures';

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

describe('inserting a block', () => {
    it('should insert a block into the children of a block at an index', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b')])]);

        expect(store.getState().addBlock('heading', 'g', 1)).toBe('new-1');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a', 'new-1', 'b']]);
        expect(store.getState().selectedKey).toBe('new-1');
    });

    it('should add the only block type a container allows straight away, as many as its min needs', () => {
        const store = storeWith();

        store.getState().addBlock('columns');

        const [inserted] = store.getState().entry.tree.blocks;
        expect(inserted?.children.map((child) => child.type)).toEqual(['column', 'column']);
        expect(shape(store.getState().entry.tree.blocks)).toEqual(['new-1', ['new-2', 'new-3']]);
        expect(store.getState().selectedKey).toBe('new-1');
        expect(store.getState().history).toHaveLength(1);
    });

    it('should add one Column at a time into Columns, up to its max', () => {
        const store = storeWith([blockOf('columns', 'cols', [blockOf('column', 'c1'), blockOf('column', 'c2'), blockOf('column', 'c3')])]);

        expect(store.getState().addBlock('column', 'cols')).toBe('new-1');
        expect(store.getState().addBlock('column', 'cols')).toBeNull();

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['cols', ['c1', 'c2', 'c3', 'new-1']]);
    });

    it('should refuse a block type the spot does not allow', () => {
        const store = storeWith([block('h')]);

        expect(store.getState().addBlock('column')).toBeNull();
        expect(store.getState().addBlock('heading', 'h')).toBeNull();
        expect(store.getState().history).toHaveLength(0);
    });

    it('should refuse a disabled block type', () => {
        const store = storeWith([], blockTypes.map((type) => (type === spacer ? { ...spacer, disabled: true } : type)));

        expect(store.getState().addBlock('spacer')).toBeNull();
        expect(keys(store)).toEqual([]);
    });
});

describe('moving a block in the tree', () => {
    it('should reorder the blocks of a list', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        expect(store.getState().moveBlock('a', null, 2)).toBe(true);

        expect(keys(store)).toEqual(['b', 'c', 'a']);
        expect(store.getState().history).toHaveLength(1);
    });

    it('should nest a block, with its children, inside another', () => {
        const store = storeWith([blockOf('group', 'g', [block('a')]), blockOf('columns', 'cols', [blockOf('column', 'c1'), blockOf('column', 'c2')])]);

        expect(store.getState().moveBlock('g', 'c2', 0)).toBe(true);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['cols', ['c1', 'c2', ['g', ['a']]]]);
    });

    it('should refuse a drop the rules do not allow and leave the tree as it is', () => {
        const store = storeWith([blockOf('columns', 'cols', [blockOf('column', 'c1'), blockOf('column', 'c2')]), block('h')]);
        const before = store.getState().entry;

        expect(store.getState().moveBlock('c1', null, 0)).toBe(false);
        expect(store.getState().moveBlock('h', 'cols', 0)).toBe(false);
        expect(store.getState().moveBlock('cols', 'c1', 0)).toBe(false);

        expect(store.getState().entry).toBe(before);
        expect(store.getState().history).toHaveLength(0);
    });

    it('should keep the selection on the moved block', () => {
        const store = storeWith([block('a'), blockOf('group', 'g')]);
        store.getState().selectBlock('a');

        store.getState().moveBlock('a', 'g', 0);

        expect(store.getState().selectedKey).toBe('a');
    });
});
