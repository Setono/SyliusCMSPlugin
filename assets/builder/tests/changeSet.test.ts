import { enablePatches, produceWithPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { changesFromPatches, mergeChanges, type Change, type TranslatableLookup } from '../src/changeSet';
import type { Entry } from '../src/types';
import { block, entry, storeWith } from './fixtures';

enablePatches();

const isTranslatable: TranslatableLookup = (blockType, field) => blockType === 'heading' && field === 'text';

/** The changes of one edit made directly with Immer. */
function changesOf(before: Entry, recipe: (working: Entry) => void): Change[] {
    const [after, patches] = produceWithPatches<Entry, Entry>(before, recipe);

    return changesFromPatches(patches, before, after, isTranslatable);
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
        expect(changesFromPatches(patches, before, after, isTranslatable)).toEqual([{ op: 'remove_block', key: 'b' }]);
    });

    it('should send an added block with its parent and its index', () => {
        const store = storeWith([block('a')]);

        store.getState().addBlock('heading');

        expect(store.getState().unsaved).toEqual([{ op: 'add_block', parent: null, index: 1, block: store.getState().entry.tree.blocks[1] }]);
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
            { op: 'add_block', parent: null, index: 1, block: block('c') },
        ]);
    });

    it('should diff the children of a block as a list of their own', () => {
        const parent = { ...block('a'), children: [block('a1'), block('a2')] };
        const changes = changesOf(entry([parent]), (working) => {
            working.tree.blocks[0]!.children.splice(0, 1);
            working.tree.blocks[0]!.children.push(block('a3'));
        });

        expect(changes).toEqual([
            { op: 'remove_block', key: 'a1' },
            { op: 'add_block', parent: 'a', index: 1, block: block('a3') },
        ]);
    });

    it('should ignore changes outside the block tree', () => {
        const changes = changesOf(entry(), (working) => {
            working.translations.en_US!.fields.title = 'About';
        });

        expect(changes).toEqual([]);
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
            { op: 'add_block', parent: null, index: 1, block: expect.objectContaining({ key: 'new-2' }) },
            { op: 'remove_block', key: 'new-1' },
        ]);
    });
});
