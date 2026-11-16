import { describe, expect, it } from 'vitest';
import { accordion, accordionItem, block, blockOf, blockTypes, entry, keys, page, shape, spacer, storeWith } from './fixtures';

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
        expect(store.getState().history.done).toHaveLength(0);
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

        expect(store.getState().history.done).toHaveLength(0);
        expect(store.getState().changeCount).toBe(0);
    });

    it('should load another working copy in place of the one it edits, with nothing left to save or undo', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello world' } }), block('b')]);
        store.getState().setFieldValue('a', 'text', 'Goodbye');
        store.getState().selectBlock('a');
        const changeCount = store.getState().changeCount;

        store.getState().load(entry([block('a', { text: { en_US: 'Hello' } })]), 'published', 4);

        expect(store.getState().entry.tree.blocks).toEqual([block('a', { text: { en_US: 'Hello' } })]);
        expect(store.getState().status).toBe('published');
        expect(store.getState().lockToken).toBe(4);
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().history.done).toEqual([]);
        expect(store.getState().selectedKey).toBe('a');
        expect(store.getState().changeCount).toBe(changeCount);
    });

    it('should drop the selection when the loaded working copy does not have the selected block', () => {
        const store = storeWith([block('a'), block('b')]);
        store.getState().selectBlock('b');

        store.getState().load(entry([block('a')]), 'published', 1);

        expect(store.getState().selectedKey).toBeNull();
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
        expect(store.getState().history.done).toHaveLength(1);
    });

    it('should add an Accordion with one item straight away', () => {
        const withAccordion = { ...page, blocks: [...(page.blocks ?? []), { type: 'accordion', min: null, max: null }] };
        const store = storeWith([], [...blockTypes, accordion, accordionItem], withAccordion);

        store.getState().addBlock('accordion');

        const [inserted] = store.getState().entry.tree.blocks;
        expect(inserted?.type).toBe('accordion');
        expect(inserted?.children.map((child) => child.type)).toEqual(['accordion_item']);
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
        expect(store.getState().history.done).toHaveLength(0);
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
        expect(store.getState().history.done).toHaveLength(1);
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
        expect(store.getState().history.done).toHaveLength(0);
    });

    it('should keep the selection on the moved block', () => {
        const store = storeWith([block('a'), blockOf('group', 'g')]);
        store.getState().selectBlock('a');

        store.getState().moveBlock('a', 'g', 0);

        expect(store.getState().selectedKey).toBe('a');
    });
});

describe('selecting several blocks', () => {
    it('should add a block to the selection, and take a selected one out of it', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);
        store.getState().selectBlock('a');

        store.getState().toggleSelection('c');
        expect(store.getState().selection).toEqual(['a', 'c']);
        expect(store.getState().selectedKey).toBe('c');

        store.getState().toggleSelection('c');
        expect(store.getState().selection).toEqual(['a']);
        expect(store.getState().selectedKey).toBe('a');

        store.getState().toggleSelection('a');
        expect(store.getState().selection).toEqual([]);
        expect(store.getState().selectedKey).toBeNull();
    });

    it('should select only the one block a plain selection picks', () => {
        const store = storeWith([block('a'), block('b')]);
        store.getState().selectBlock('a');
        store.getState().toggleSelection('b');

        store.getState().selectBlock('b');
        expect(store.getState().selection).toEqual(['b']);

        store.getState().selectBlock(null);
        expect(store.getState().selection).toEqual([]);
    });

    it('should drop the blocks an edit removes from the selection', () => {
        const store = storeWith([blockOf('group', 'g', [block('a')]), block('b')]);
        store.getState().selectBlock('a');
        store.getState().toggleSelection('b');

        store.getState().removeBlock('g');

        expect(store.getState().selection).toEqual(['b']);
        expect(store.getState().selectedKey).toBe('b');
    });
});

describe('moving several blocks', () => {
    it('should move the blocks to a spot in tree order, in one edit, a block inside another of them counting once', () => {
        const store = storeWith([block('a'), blockOf('group', 'g', [block('b')]), blockOf('group', 'h', [block('c')]), block('d')]);

        expect(store.getState().moveBlocks(['d', 'c', 'h', 'a'], 'g', 0)).toBe(true);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a', 'h', ['c'], 'd', 'b']]);
        expect(store.getState().history.done).toHaveLength(1);
    });

    it('should count the index in the list once the moved blocks are taken out', () => {
        const store = storeWith([block('a'), block('b'), block('c'), block('d')]);

        store.getState().moveBlocks(['a', 'b'], null, 1);

        expect(keys(store)).toEqual(['c', 'a', 'b', 'd']);
    });

    it.each([
        ['a block type the spot does not allow', ['h', 'c1'], null],
        ['more of a block type than the spot has room for', ['f1', 'f2'], 'big'],
        ['fewer of a block type than the spot they leave needs', ['s1'], 'big'],
        ['a block into one of the moved blocks', ['h', 'g'], 'g'],
        ['a block into a block inside one of the moved blocks', ['g'], 'inner'],
        ['a block into a block the tree does not have', ['h'], 'missing'],
    ])('should refuse to move %s and leave the tree as it is', (_, moved, parent) => {
        const columns = (key: string, ...children: string[]) =>
            blockOf(
                'columns',
                key,
                children.map((child) => blockOf('column', child)),
            );
        const store = storeWith([
            block('h'),
            blockOf('group', 'g', [blockOf('group', 'inner')]),
            columns('big', 'c1', 'c2', 'c3'),
            columns('small', 's1', 's2'),
            columns('full', 'f1', 'f2', 'f3', 'f4'),
        ]);
        const before = store.getState().entry;

        expect(store.getState().moveBlocks(moved, parent, 0)).toBe(false);

        expect(store.getState().entry).toBe(before);
        expect(store.getState().history.done).toHaveLength(0);
    });
});

describe('moving blocks up and down', () => {
    it('should move a block one place up or down in its list', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        expect(store.getState().shiftBlocks(['c'], 'up')).toBe(true);
        expect(keys(store)).toEqual(['a', 'c', 'b']);

        expect(store.getState().shiftBlocks(['a'], 'down')).toBe(true);
        expect(keys(store)).toEqual(['c', 'a', 'b']);
    });

    it('should move every selected block past the block next to it, each in its own list, in one edit', () => {
        const store = storeWith([block('a'), block('b'), blockOf('group', 'g', [block('c'), block('d')]), block('e')]);

        expect(store.getState().shiftBlocks(['b', 'e', 'd'], 'up')).toBe(true);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['b', 'a', 'e', 'g', ['d', 'c']]);
        expect(store.getState().history.done).toHaveLength(1);
    });

    it('should move a block inside another selected block with that one', () => {
        const store = storeWith([blockOf('group', 'g', [block('c'), block('d')]), block('e')]);

        store.getState().shiftBlocks(['g', 'd'], 'down');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['e', 'g', ['c', 'd']]);
    });

    it('should leave a block at the end of its list where it is, and refuse when none can move', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        expect(store.getState().shiftBlocks(['a', 'c'], 'up')).toBe(true);
        expect(keys(store)).toEqual(['a', 'c', 'b']);

        expect(store.getState().shiftBlocks(['a', 'c'], 'up')).toBe(false);
        expect(store.getState().history.done).toHaveLength(1);
    });
});

describe('deleting several blocks', () => {
    it('should delete every selected block in one edit, a block inside another selected block counting once', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b')]), block('c'), block('d')]);

        store.getState().removeBlocks(['a', 'd', 'g']);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['c']);
        expect(store.getState().history.done).toHaveLength(1);
        expect(store.getState().unsaved).toEqual([
            { op: 'remove_block', key: 'g' },
            { op: 'remove_block', key: 'd' },
        ]);
    });
});

describe('duplicating blocks', () => {
    it('should put a copy right after the block, with a new key for it and every block inside it, and select the copy', () => {
        const nested = blockOf('group', 'g', [block('a', { text: { en_US: 'Inside' } }), blockOf('columns', 'cols', [blockOf('column', 'c1'), blockOf('column', 'c2')])], {
            fullWidth: true,
        });
        const store = storeWith([nested, block('z')]);

        expect(store.getState().duplicateBlocks(['g'])).toEqual(['new-1']);

        const [original, copy] = store.getState().entry.tree.blocks;
        expect(original).toEqual(nested);
        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a', 'cols', ['c1', 'c2']], 'new-1', ['new-2', 'new-3', ['new-4', 'new-5']], 'z']);
        expect(copy).toEqual({
            ...nested,
            key: 'new-1',
            children: [
                { ...block('a', { text: { en_US: 'Inside' } }), key: 'new-2' },
                { ...blockOf('columns', 'new-3', [blockOf('column', 'new-4'), blockOf('column', 'new-5')]) },
            ],
        });
        expect(store.getState().selection).toEqual(['new-1']);
        expect(store.getState().history.done).toHaveLength(1);
    });

    it('should copy each selected block next to its own, a block inside another selected block counting once', () => {
        const store = storeWith([blockOf('group', 'g', [block('a')]), blockOf('group', 'h', [block('b')])]);

        expect(store.getState().duplicateBlocks(['b', 'a', 'g'])).toEqual(['new-1', 'new-3']);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a'], 'new-1', ['new-2'], 'h', ['b', 'new-3']]);
        expect(store.getState().selection).toEqual(['new-1', 'new-3']);
    });

    it('should refuse to copy a block where the nesting rules have no room for another', () => {
        const columns = [blockOf('column', 'c1'), blockOf('column', 'c2'), blockOf('column', 'c3'), blockOf('column', 'c4')];
        const store = storeWith([blockOf('columns', 'cols', columns), block('a')]);

        expect(store.getState().duplicateBlocks(['a', 'c1'])).toBeNull();

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['cols', ['c1', 'c2', 'c3', 'c4'], 'a']);
        expect(store.getState().history.done).toHaveLength(0);
    });

    it('should undo a duplicate in one step', () => {
        const store = storeWith([blockOf('group', 'g', [block('a')])]);
        store.getState().duplicateBlocks(['g']);

        store.getState().undo();

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a']]);
        expect(store.getState().selection).toEqual([]);
    });
});

describe('undo and redo', () => {
    it('should undo tree and field edits one at a time, and redo them', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } }), blockOf('group', 'g')]);
        const states = [store.getState().entry];
        const edits = [
            () => store.getState().addBlock('heading', 'g'),
            () => store.getState().setFieldValue('a', 'level', 'h3'),
            () => store.getState().moveBlock('a', 'g', 0),
            () => store.getState().removeBlock('new-1'),
        ];
        for (const edit of edits) {
            edit();
            states.push(store.getState().entry);
        }

        for (let i = edits.length - 1; i >= 0; i--) {
            expect(store.getState().undo()).toBe(true);
            expect(store.getState().entry).toEqual(states[i]);
        }
        expect(store.getState().undo()).toBe(false);

        for (let i = 1; i <= edits.length; i++) {
            expect(store.getState().redo()).toBe(true);
            expect(store.getState().entry).toEqual(states[i]);
        }
        expect(store.getState().redo()).toBe(false);
    });

    it('should leave what undo and redo change to save, like any other edit', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } })]);
        store.getState().addBlock('heading');
        store.getState().setFieldValue('a', 'text', 'Hello world');
        // As if autosave had sent them
        store.setState({ unsaved: [] });
        const changeCount = store.getState().changeCount;

        store.getState().undo();
        store.getState().undo();

        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' },
            { op: 'remove_block', key: 'new-1' },
        ]);
        expect(store.getState().changeCount).toBe(changeCount + 2);

        store.getState().redo();

        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' },
            { op: 'remove_block', key: 'new-1' },
            { op: 'add_block', parent: null, index: 1, after: 'a', block: expect.objectContaining({ key: 'new-1' }) },
        ]);
    });

    it('should undo typing in one field in one step, and an edit of another field on its own', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } })]);

        for (const text of ['Hello w', 'Hello wo', 'Hello wor']) {
            store.getState().setFieldValue('a', 'text', text);
        }
        store.getState().setFieldValue('a', 'level', 'h3');
        store.getState().setFieldValue('a', 'text', 'Hello world');

        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello wor' }, level: 'h3' });
        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello wor' } });
        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Hello' } });
    });

    it('should drop the selection of a block that undo takes away', () => {
        const store = storeWith([block('a')]);
        store.getState().addBlock('heading');
        expect(store.getState().selectedKey).toBe('new-1');

        store.getState().undo();

        expect(store.getState().selectedKey).toBeNull();
    });
});
