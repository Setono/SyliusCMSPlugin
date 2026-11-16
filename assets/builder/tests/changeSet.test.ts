import { enablePatches, produceWithPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { changesFromPatches, mergeChanges, type Change, type TranslatableLookup } from '../src/changeSet';
import type { Entry, StoredBlock } from '../src/types';
import { writeFieldValue } from '../src/values';
import { block, blockOf, entry, shape, storeWith } from './fixtures';

enablePatches();

const isTranslatable: TranslatableLookup = (blockType, field) => blockType === 'heading' && field === 'text';

/** Applies block additions, moves, removals, values and styles in order, the way the server does. */
function replay(blocks: StoredBlock[], changes: readonly Change[]): StoredBlock[] {
    const tree = structuredClone(blocks);
    const find = (list: StoredBlock[], key: string): StoredBlock | undefined =>
        list.find((candidate) => candidate.key === key) ?? list.map((candidate) => find(candidate.children, key)).find((found) => found !== undefined);
    const listOf = (parent: string | null): StoredBlock[] => (parent === null ? tree : (find(tree, parent)?.children ?? []));
    const takeOut = (list: StoredBlock[], key: string): StoredBlock | undefined => {
        const index = list.findIndex((candidate) => candidate.key === key);
        if (index !== -1) {
            return list.splice(index, 1)[0];
        }

        return list.map((candidate) => takeOut(candidate.children, key)).find((found) => found !== undefined);
    };

    for (const change of changes) {
        if (change.op === 'add_block') {
            listOf(change.parent).splice(change.index, 0, structuredClone(change.block));
        } else if (change.op === 'move_block') {
            const moved = takeOut(tree, change.key);
            if (moved !== undefined) {
                listOf(change.parent).splice(change.index, 0, moved);
            }
        } else if (change.op === 'remove_block') {
            takeOut(tree, change.key);
        } else if (change.op === 'set_style') {
            const target = find(tree, change.key);
            if (target !== undefined) {
                target.style = change.style;
            }
        } else if (change.op === 'set_value') {
            const target = find(tree, change.key);
            if (target !== undefined) {
                writeFieldValue(target.fields, change.field, change.locale !== null, change.locale ?? '', change.value);
            }
        }
    }

    return tree;
}

/** The changes of one edit made directly with Immer. */
function changesOf(before: Entry, recipe: (working: Entry) => void): Change[] {
    const [after, patches] = produceWithPatches<Entry, Entry>(before, recipe);

    return changesFromPatches(patches, before, after, isTranslatable, () => false);
}

describe('building the change set', () => {
    it('should send typing over several edits as one value', () => {
        const store = storeWith([block('a')]);

        for (const text of ['H', 'He', 'Hel', 'Hell', 'Hello']) {
            store.getState().setFieldValue('a', 'text', text);
        }

        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' }]);
    });

    it('should send nothing for a block added and removed again before a save', () => {
        const store = storeWith([block('a')]);

        const key = store.getState().addBlock('heading') ?? '';
        store.getState().setFieldValue(key, 'text', 'Hello');
        store.getState().removeBlock(key);

        expect(store.getState().unsaved).toEqual([]);
    });

    it('should send one removal for a block removed from the middle of three', () => {
        const before = entry([block('a'), block('b'), block('c')]);
        const [after, patches] = produceWithPatches<Entry, Entry>(before, (working) => {
            working.tree.blocks.splice(1, 1);
        });

        // Immer reports this as replacing index 1 and removing the last index.
        expect(patches.map((patch) => patch.op)).toEqual(['replace', 'remove']);
        expect(changesFromPatches(patches, before, after, isTranslatable, () => false)).toEqual([{ op: 'remove_block', key: 'b' }]);
    });

    it('should send an added block with its parent and its index', () => {
        const store = storeWith([block('a')]);

        store.getState().addBlock('heading');

        expect(store.getState().unsaved).toEqual([{ op: 'add_block', parent: null, index: 1, after: 'a', block: store.getState().entry.tree.blocks[1] }]);
    });

    it('should send a translatable value with the edited locale and a shared value with no locale', () => {
        const store = storeWith([block('a')]);

        store.getState().setFieldValue('a', 'text', 'Hello');
        store.getState().setFieldValue('a', 'level', 'h3');

        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' },
            { op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' },
        ]);
    });

    it('should send a cleared text field as null', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } })]);

        store.getState().setFieldValue('a', 'text', '');

        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: null }]);
    });

    it('should send a value set on a new block after the block itself', () => {
        const store = storeWith();

        store.getState().addBlock('heading');
        store.getState().setFieldValue('new-1', 'text', 'Hello');

        expect(store.getState().unsaved.map((change) => change.op)).toEqual(['add_block', 'set_value']);
    });

    it('should send one change per locale when a locale map is replaced as a whole', () => {
        const changes = changesOf(entry([block('a', { text: { da_DK: 'Hej', en_US: 'Hello' } })]), (working) => {
            working.tree.blocks[0]!.fields.text = { de_DE: 'Hallo', en_US: 'Hello' };
        });

        expect(changes).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: null },
            { op: 'set_value', key: 'a', field: 'text', locale: 'de_DE', value: 'Hallo' },
        ]);
    });

    it('should compare keys and values when the whole list of blocks is replaced', () => {
        const changes = changesOf(entry([block('a'), block('b')]), (working) => {
            working.tree.blocks = [block('a', { level: 'h4' }), block('c')];
        });

        expect(changes).toEqual([
            { op: 'remove_block', key: 'b' },
            { op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h4' },
            { op: 'add_block', parent: null, index: 1, after: 'a', block: block('c') },
        ]);
    });

    it('should compare styles when the whole list of blocks is replaced', () => {
        const changes = changesOf(entry([{ ...blockOf('group', 'g'), style: 'muted' }, blockOf('group', 'h')]), (working) => {
            working.tree.blocks = [blockOf('group', 'g'), { ...blockOf('group', 'h'), style: 'dark' }];
        });

        expect(changes).toEqual([
            { op: 'set_style', key: 'g', style: null },
            { op: 'set_style', key: 'h', style: 'dark' },
        ]);
    });

    it('should send a style set on a block as a set_style change, and nothing when it stays the same', () => {
        const before = entry([{ ...blockOf('group', 'g'), style: 'dark' }]);

        expect(
            changesOf(before, (working) => {
                working.tree.blocks[0]!.style = 'muted';
            }),
        ).toEqual([{ op: 'set_style', key: 'g', style: 'muted' }]);
        expect(
            changesOf(before, (working) => {
                working.tree.blocks[0]!.style = 'muted';
                working.tree.blocks[0]!.style = 'dark';
            }),
        ).toEqual([]);
    });

    it('should diff the children of a block as a list of their own', () => {
        const parent = { ...block('a'), children: [block('a1'), block('a2')] };
        const changes = changesOf(entry([parent]), (working) => {
            working.tree.blocks[0]!.children.splice(0, 1);
            working.tree.blocks[0]!.children.push(block('a3'));
        });

        expect(changes).toEqual([
            { op: 'remove_block', key: 'a1' },
            { op: 'add_block', parent: 'a', index: 1, after: 'a2', block: block('a3') },
        ]);
    });

    it("should turn an edit of a translation's field into a change of its value in its locale", () => {
        const changes = changesOf(entry(), (working) => {
            working.translations.en_US!.fields.title = 'About';
        });

        expect(changes).toEqual([{ op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About' }]);
    });

    it('should empty the fields of a translation taken away, so the entry has no translation there', () => {
        const changes = changesOf(entry(), (working) => {
            delete working.translations.en_US;
        });

        expect(changes).toEqual([
            { op: 'set_entry_value', field: 'slug', locale: 'en_US', value: null },
            { op: 'set_entry_value', field: 'title', locale: 'en_US', value: null },
        ]);
    });

    it('should send a value of the entry that all translations share with no locale', () => {
        const changes = changesOf({ ...entry(), fields: { noindex: false } }, (working) => {
            working.fields.noindex = true;
        });

        expect(changes).toEqual([{ op: 'set_entry_value', field: 'noindex', locale: null, value: true }]);
    });

    it('should send every shared value of the entry that changed when its fields are replaced as a whole', () => {
        const changes = changesOf({ ...entry(), fields: { noindex: true, featured: true } }, (working) => {
            working.fields = { noindex: true, featured: false };
        });

        expect(changes).toEqual([{ op: 'set_entry_value', field: 'featured', locale: null, value: false }]);
    });

    it('should ignore changes outside the block tree, the translations and the entry\'s shared values', () => {
        const changes = changesOf(entry(), (working) => {
            working.createdAt = '2026-11-15T10:00:00Z';
        });

        expect(changes).toEqual([]);
    });
});

describe('sending moves', () => {
    it('should send a reorder as one move to the index once the block is taken out', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        store.getState().moveBlock('a', null, 2);

        expect(store.getState().unsaved).toEqual([{ op: 'move_block', key: 'a', parent: null, index: 2, after: 'c' }]);
    });

    it('should name the block an added or moved block goes right after, so that a merged save keeps it there', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        store.getState().moveBlock('a', null, 2);
        store.getState().addBlock('heading', null, 1);

        expect(store.getState().unsaved).toEqual([
            { op: 'move_block', key: 'a', parent: null, index: 2, after: 'c' },
            { op: 'add_block', parent: null, index: 1, after: 'b', block: expect.objectContaining({ key: 'new-1' }) },
        ]);
    });

    it('should send a move to the front of a list as one move', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);

        store.getState().moveBlock('c', null, 0);

        expect(store.getState().unsaved).toEqual([{ op: 'move_block', key: 'c', parent: null, index: 0 }]);
    });

    it('should send a block nested into another as one move with its parent', () => {
        const store = storeWith([block('h'), blockOf('columns', 'cols', [blockOf('column', 'c1', [block('x')]), blockOf('column', 'c2')])]);

        store.getState().moveBlock('h', 'c1', 1);

        expect(store.getState().unsaved).toEqual([{ op: 'move_block', key: 'h', parent: 'c1', index: 1, after: 'x' }]);
    });

    it('should send a block moved out of a container that comes before it in the list as one move', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b'), block('c')]), block('d')]);

        store.getState().moveBlock('a', null, 2);

        expect(store.getState().unsaved).toEqual([{ op: 'move_block', key: 'a', parent: null, index: 2, after: 'd' }]);
    });

    it('should send a block moved between two containers as one move', () => {
        const store = storeWith([blockOf('group', 'g1', [block('a'), block('b')]), blockOf('group', 'g2', [block('c')])]);

        store.getState().moveBlock('b', 'g2', 0);

        expect(store.getState().unsaved).toEqual([{ op: 'move_block', key: 'b', parent: 'g2', index: 0 }]);
    });

    it('should send an inserted container with the children it gets straight away as one addition', () => {
        const store = storeWith();

        store.getState().addBlock('columns');

        expect(store.getState().unsaved).toEqual([
            {
                op: 'add_block',
                parent: null,
                index: 0,
                block: expect.objectContaining({ key: 'new-1', children: [expect.objectContaining({ key: 'new-2' }), expect.objectContaining({ key: 'new-3' })] }),
            },
        ]);
    });

    it('should send as few moves as replay to the same tree when a whole tree is replaced at once', () => {
        const before = [block('a'), blockOf('group', 'g', [block('b'), block('c')]), block('d')];
        const after = [block('d'), blockOf('group', 'g', [block('c'), block('a')]), block('b')];

        const changes = changesOf(entry(before), (working) => {
            working.tree.blocks = after;
        });

        expect(changes.map((change) => change.op)).toEqual(['move_block', 'move_block', 'move_block']);
        expect(shape(replay(before, changes))).toEqual(shape(after));
    });
});

describe('sending the editing tools', () => {
    const start = (): StoredBlock[] => [block('a'), blockOf('group', 'g', [block('b'), block('c')]), blockOf('group', 'h', [block('d')]), block('e')];

    it.each<[string, (store: ReturnType<typeof storeWith>) => void]>([
        ['several blocks moved into a container', (store) => store.getState().moveBlocks(['e', 'a', 'd'], 'g', 1)],
        ['several blocks moved up and down', (store) => store.getState().shiftBlocks(['h', 'c', 'e'], 'up') && store.getState().shiftBlocks(['a', 'b'], 'down')],
        ['several blocks duplicated', (store) => store.getState().duplicateBlocks(['g', 'd', 'e'])],
        ['several blocks deleted', (store) => store.getState().removeBlocks(['a', 'c', 'h'])],
        [
            'a delete undone and redone',
            (store) => {
                store.getState().removeBlocks(['a', 'e']);
                store.getState().undo();
                store.getState().redo();
            },
        ],
        [
            'a field edited, the block deleted, the delete undone and the field edited again',
            (store) => {
                store.getState().setFieldValue('b', 'text', 'Before');
                store.getState().removeBlocks(['b']);
                store.getState().undo();
                store.getState().setFieldValue('b', 'text', 'After');
            },
        ],
        [
            'styles picked, a styled block deleted and duplicated, and undone',
            (store) => {
                store.getState().setBlockStyle('g', 'dark');
                store.getState().setBlockStyle('h', 'muted');
                store.getState().duplicateBlocks(['g']);
                store.getState().setBlockStyle('new-1', null);
                store.getState().removeBlocks(['h']);
                store.getState().undo();
                store.getState().setBlockStyle('h', 'dark');
            },
        ],
        [
            'edits undone and redone',
            (store) => {
                store.getState().duplicateBlocks(['g']);
                store.getState().moveBlocks(['a', 'new-1'], 'h', 0);
                store.getState().removeBlocks(['b']);
                store.getState().undo();
                store.getState().undo();
                store.getState().redo();
                store.getState().undo();
                store.getState().undo();
                store.getState().redo();
            },
        ],
    ])('should send changes that take the saved tree to the working copy: %s', (_, act) => {
        const store = storeWith(start());

        act(store);

        expect(store.getState().unsaved.length).toBeGreaterThan(0);
        expect(replay(start(), store.getState().unsaved)).toEqual(store.getState().entry.tree.blocks);
    });
});

describe('merging change sets', () => {
    it('should keep the last value per block, field and locale', () => {
        const merged = mergeChanges(
            [
                { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hi' },
                { op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: 'Hej' },
            ],
            [{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' }],
        );

        expect(merged).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: 'Hej' },
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' },
        ]);
    });

    it('should drop the values of a saved block that gets removed', () => {
        const merged = mergeChanges(
            [
                { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' },
                { op: 'set_value', key: 'b', field: 'text', locale: 'en_US', value: 'Hi' },
            ],
            [{ op: 'remove_block', key: 'a' }],
        );

        expect(merged).toEqual([
            { op: 'set_value', key: 'b', field: 'text', locale: 'en_US', value: 'Hi' },
            { op: 'remove_block', key: 'a' },
        ]);
    });

    it('should drop a new block with everything for it and for the blocks added into it', () => {
        const merged = mergeChanges(
            [
                { op: 'add_block', parent: null, index: 0, block: block('a') },
                { op: 'set_value', key: 'a', field: 'level', locale: null, value: 'h3' },
                { op: 'add_block', parent: 'a', index: 0, block: block('a1') },
                { op: 'set_value', key: 'a1', field: 'level', locale: null, value: 'h4' },
                { op: 'set_value', key: 'b', field: 'level', locale: null, value: 'h1' },
            ],
            [{ op: 'remove_block', key: 'a' }],
        );

        expect(merged).toEqual([{ op: 'set_value', key: 'b', field: 'level', locale: null, value: 'h1' }]);
    });

    it('should keep a new block and its removal when a later block was added after it into the same list', () => {
        const store = storeWith();

        store.getState().addBlock('heading');
        store.getState().addBlock('heading');
        store.getState().removeBlock('new-1');

        // new-2's index counts new-1, so new-1 must reach the server before it's removed.
        expect(store.getState().unsaved).toEqual([
            { op: 'add_block', parent: null, index: 0, block: expect.objectContaining({ key: 'new-1' }) },
            { op: 'add_block', parent: null, index: 1, after: 'new-1', block: expect.objectContaining({ key: 'new-2' }) },
            { op: 'remove_block', key: 'new-1' },
        ]);
    });

    it('should keep a new block and its removal when a block was moved after it', () => {
        const store = storeWith([block('a'), blockOf('group', 'g')]);

        store.getState().addBlock('heading', 'g');
        store.getState().moveBlock('a', 'g', 1);
        store.getState().removeBlock('new-1');

        expect(store.getState().unsaved).toEqual([
            { op: 'add_block', parent: 'g', index: 0, block: expect.objectContaining({ key: 'new-1' }) },
            { op: 'move_block', key: 'a', parent: 'g', index: 1, after: 'new-1' },
            { op: 'remove_block', key: 'new-1' },
        ]);
    });
});
