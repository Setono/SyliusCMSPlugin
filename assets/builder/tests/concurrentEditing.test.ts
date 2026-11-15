import { describe, expect, it } from 'vitest';
import type { Entry } from '../src/types';
import { block, blockOf, entry, shape, storeWith } from './fixtures';

/** What the server answers after merging a save onto a colleague's: the working copy, with their changes in it. */
function merged(build: (copy: Entry) => void, from: Entry): Entry {
    const copy = structuredClone(from);
    build(copy);

    return copy;
}

describe('pulling in a colleague\'s changes', () => {
    it('should take their changes into the working copy, with nothing left to save', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } }), block('b', { text: { en_US: 'World' } })]);
        const theirs = merged((copy) => {
            copy.tree.blocks[1]!.fields.text = { en_US: 'Wide world' };
            copy.tree.blocks.push(block('c'));
        }, store.getState().entry);

        store.getState().pullIn(theirs);

        expect(store.getState().entry).toEqual(theirs);
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should keep the edits their changes leave alone on the undo stack', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello' } }), blockOf('group', 'g', [block('b', { text: { en_US: 'World' } })])]);
        store.getState().setFieldValue('a', 'text', 'Hello there');
        // As if autosave had sent it
        store.setState({ unsaved: [] });
        const theirs = merged((copy) => {
            copy.tree.blocks[1]!.children[0]!.fields.level = 'h3';
        }, store.getState().entry);

        store.getState().pullIn(theirs);
        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]!.fields.text).toEqual({ en_US: 'Hello' });
        expect(store.getState().entry.tree.blocks[1]!.children[0]!.fields.level).toBe('h3');
        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello' }]);
    });

    it('should drop the edits their changes touch from the undo stack, and the edits before them', () => {
        const store = storeWith([block('a'), block('b', { text: { en_US: 'World' } }), block('c')]);
        store.getState().setFieldValue('a', 'level', 'h3');
        store.getState().setFieldValue('b', 'text', 'Wide world');
        store.getState().setFieldValue('c', 'level', 'h4');
        store.setState({ unsaved: [] });
        const theirs = merged((copy) => {
            copy.tree.blocks[1]!.fields.text = { en_US: 'Their world' };
        }, store.getState().entry);

        store.getState().pullIn(theirs);

        expect(store.getState().history.done).toHaveLength(1);
        expect(store.getState().undo()).toBe(true);
        expect(store.getState().entry.tree.blocks[2]!.fields).toEqual({});
        expect(store.getState().undo()).toBe(false);
    });

    it('should apply the changes not yet saved on top of theirs, the way the server will', () => {
        const store = storeWith([block('a'), block('b'), block('c')]);
        // Made while the save that brings their changes was in flight
        store.getState().setFieldValue('a', 'text', 'Mine');
        store.getState().moveBlock('a', null, 2);
        const theirs = merged((copy) => {
            copy.tree.blocks.splice(2, 0, block('d', { text: { en_US: 'Theirs' } }));
        }, entry([block('a'), block('b'), block('c')]));

        store.getState().pullIn(theirs);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['b', 'd', 'c', 'a']);
        expect(store.getState().entry.tree.blocks[3]!.fields.text).toEqual({ en_US: 'Mine' });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Mine' },
            { op: 'move_block', key: 'a', parent: null, index: 2, after: 'c' },
        ]);
    });

    it('should apply a block style not yet saved on top of theirs', () => {
        const store = storeWith([blockOf('group', 'g')]);
        store.getState().setBlockStyle('g', 'dark');

        store.getState().pullIn(entry([blockOf('group', 'g', [], { fullWidth: true })]));

        expect(store.getState().entry.tree.blocks[0]).toEqual({ ...blockOf('group', 'g', [], { fullWidth: true }), style: 'dark' });
        expect(store.getState().unsaved).toEqual([{ op: 'set_style', key: 'g', style: 'dark' }]);
    });

    it('should move blocks between lists the way they did', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b')]), blockOf('group', 'h', [block('c')])]);
        const theirs = merged((copy) => {
            const [a] = copy.tree.blocks[0]!.children.splice(0, 1);
            copy.tree.blocks[1]!.children.unshift(a!);
            copy.tree.blocks.reverse();
        }, store.getState().entry);

        store.getState().pullIn(theirs);

        expect(store.getState().entry).toEqual(theirs);
        expect(shape(store.getState().entry.tree.blocks)).toEqual(['h', ['a', 'c'], 'g', ['b']]);
    });

    it('should hold a value not yet saved as a clash when they changed its field and locale since the save was sent', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello', da_DK: 'Hej' } })]);
        const sent = store.getState().entry;
        store.getState().setFieldValue('a', 'text', 'Mine');
        const theirs = merged((copy) => {
            copy.tree.blocks[0]!.fields.text = { en_US: 'Theirs', da_DK: 'Hej' };
        }, sent);

        store.getState().pullIn(theirs, [], sent);

        expect(store.getState().clashes).toEqual([{ kind: 'value', key: 'a', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' }]);
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should keep a value not yet saved when they changed another locale of its field, or set the same value', () => {
        const store = storeWith([block('a', { text: { en_US: 'Hello', da_DK: 'Hej' } }), block('b', { text: { en_US: 'World' } })]);
        const sent = store.getState().entry;
        store.getState().setFieldValue('a', 'text', 'Mine');
        store.getState().setFieldValue('b', 'text', 'Same');
        const theirs = merged((copy) => {
            copy.tree.blocks[0]!.fields.text = { en_US: 'Hello', da_DK: 'Deres' };
            copy.tree.blocks[1]!.fields.text = { en_US: 'Same' };
        }, sent);

        store.getState().pullIn(theirs, [], sent);

        expect(store.getState().clashes).toEqual([]);
        expect(store.getState().entry.tree.blocks[0]!.fields.text).toEqual({ en_US: 'Mine', da_DK: 'Deres' });
        expect(store.getState().unsaved).toEqual([
            { op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Mine' },
            { op: 'set_value', key: 'b', field: 'text', locale: 'en_US', value: 'Same' },
        ]);
    });

    it('should drop a change not yet saved to a block they deleted, and hold it as a clash', () => {
        const store = storeWith([block('a'), block('b')]);
        store.getState().setFieldValue('b', 'text', 'Mine');
        const theirs = merged((copy) => {
            copy.tree.blocks.splice(1, 1);
        }, entry([block('a'), block('b')]));

        store.getState().pullIn(theirs);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a']);
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().clashes).toEqual([expect.objectContaining({ kind: 'deleted_block', key: 'b' })]);
    });

    it('should drop the selection of a block they deleted', () => {
        const store = storeWith([block('a'), block('b')]);
        store.getState().selectBlock('b');

        store.getState().pullIn(entry([block('a')]));

        expect(store.getState().selectedKey).toBeNull();
    });

    it('should count as a change to the working copy, so the preview shows it', () => {
        const store = storeWith([block('a')]);
        const changeCount = store.getState().changeCount;

        store.getState().pullIn(entry([block('a', { level: 'h3' })]));

        expect(store.getState().changeCount).toBe(changeCount + 1);
    });
});

describe('resolving clashes', () => {
    it('should hold the clashes and resolve them one at a time, keeping mine or taking theirs', () => {
        const store = storeWith([block('a', { text: { en_US: 'Mine' } }), block('b')]);
        const theirs = entry([block('a', { text: { en_US: 'Theirs' } }), block('b', { level: 'h3' })]);

        store.getState().pullIn(theirs, [
            { kind: 'value', key: 'a', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' },
            { kind: 'changed_block', key: 'b' },
        ]);

        expect(store.getState().entry).toEqual(theirs);
        expect(store.getState().clashes.map((clash) => clash.kind)).toEqual(['value', 'changed_block']);

        store.getState().resolveClash('mine');

        expect(store.getState().entry.tree.blocks[0]!.fields.text).toEqual({ en_US: 'Mine' });
        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Mine' }]);
        expect(store.getState().clashes.map((clash) => clash.kind)).toEqual(['changed_block']);

        store.getState().resolveClash('theirs');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a', 'b']);
        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Mine' }]);
        expect(store.getState().clashes).toEqual([]);
    });

    it('should delete the block a colleague changed when the editor keeps their deletion', () => {
        const store = storeWith([block('a')]);
        store.getState().pullIn(entry([block('a'), block('b', { level: 'h3' })]), [{ kind: 'changed_block', key: 'b' }]);

        store.getState().resolveClash('mine');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a']);
        expect(store.getState().unsaved).toEqual([{ op: 'remove_block', key: 'b' }]);
    });

    it('should put the block a colleague deleted back where it was, as the editor had it, when they keep theirs', () => {
        const store = storeWith([block('a'), blockOf('group', 'g', [block('b', { text: { en_US: 'Mine' } })]), block('c')]);

        store.getState().pullIn(entry([block('a'), block('c')]), [{ kind: 'deleted_block', key: 'b' }]);

        expect(store.getState().clashes).toHaveLength(1);
        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a', 'c']);

        store.getState().resolveClash('mine');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a', 'g', ['b'], 'c']);
        expect(store.getState().entry.tree.blocks[1]!.children[0]!.fields.text).toEqual({ en_US: 'Mine' });
        expect(store.getState().unsaved).toEqual([{ op: 'add_block', parent: null, index: 1, after: 'a', block: expect.objectContaining({ key: 'g' }) }]);
    });

    it('should leave the block deleted when the editor takes theirs', () => {
        const store = storeWith([block('a'), block('b', { text: { en_US: 'Mine' } })]);
        store.getState().pullIn(entry([block('a')]), [{ kind: 'deleted_block', key: 'b' }]);

        store.getState().resolveClash('theirs');

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a']);
        expect(store.getState().unsaved).toEqual([]);
        expect(store.getState().clashes).toEqual([]);
    });

    it('should hold one clash for the edits to blocks inside one deleted block', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b')])]);

        store.getState().pullIn(entry([]), [
            { kind: 'deleted_block', key: 'a' },
            { kind: 'deleted_block', key: 'b' },
        ]);

        expect(store.getState().clashes).toEqual([expect.objectContaining({ kind: 'deleted_block', key: 'g' })]);
    });

    it('should undo keeping mine like any other edit', () => {
        const store = storeWith([block('a', { text: { en_US: 'Mine' } })]);
        store.getState().pullIn(entry([block('a', { text: { en_US: 'Theirs' } })]), [
            { kind: 'value', key: 'a', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' },
        ]);
        store.getState().resolveClash('mine');

        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]!.fields.text).toEqual({ en_US: 'Theirs' });
    });

    it('should do nothing when there is no clash', () => {
        const store = storeWith([block('a')]);
        const before = store.getState().entry;

        store.getState().resolveClash('mine');

        expect(store.getState().entry).toBe(before);
    });
});
