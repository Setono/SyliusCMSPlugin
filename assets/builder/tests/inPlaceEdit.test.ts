import { describe, expect, it, vi } from 'vitest';
import type { BridgeStrings } from '../src/bridge/protocol';
import { createPreviewSync } from '../src/previewSync';
import type { BuilderStore } from '../src/store';
import type { JsonObject } from '../src/types';
import { accordion, accordionItem, block, blockOf, blockTypes, storeWith } from './fixtures';

const strings: BridgeStrings = {
    selectParent: 'Select parent',
    settings: 'Settings',
    delete: 'Delete block',
    addBefore: 'Add block above',
    addAfter: 'Add block below',
    moveUp: 'Move up',
    moveDown: 'Move down',
    duplicate: 'Duplicate',
    formatting: 'Formatting',
    bold: 'Bold',
    italic: 'Italic',
    underline: 'Underline',
    strike: 'Strikethrough',
    code: 'Code',
};

/** Hears what the bridge in the preview sends, as the builder does. */
function listen(store: BuilderStore) {
    return createPreviewSync({ store, strings, render: vi.fn(async () => ({})), reload: vi.fn(), openBlockPicker: vi.fn(), openSettings: vi.fn() });
}

function doc(text: string, marks: string[] = []): JsonObject {
    const node: JsonObject = marks.length === 0 ? { type: 'text', text } : { type: 'text', text, marks: marks.map((type) => ({ type })) };

    return { type: 'doc', content: [{ type: 'paragraph', content: [node] }] };
}

function accordionWith(content: JsonObject | null = null) {
    const item = blockOf('accordion_item', 'item', [], { title: { en_US: 'Shipping' }, ...(content === null ? {} : { content: { en_US: content } }) });

    return storeWith([blockOf('accordion', 'faq', [item])], [...blockTypes, accordion, accordionItem]);
}

describe('an edit typed in place in the preview', () => {
    it('should write the text to the field in the edited locale, as an edit that autosaves and undoes like one in the right pane', () => {
        const store = storeWith([block('a', { text: { en_US: 'Welcome', da_DK: 'Velkommen' }, level: 'h2' })]);
        const before = store.getState().entry;

        listen(store).receive({ type: 'edit', key: 'a', field: 'text', value: 'Welcome back' });

        const { entry, unsaved, revision } = store.getState();
        expect(entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Welcome back', da_DK: 'Velkommen' }, level: 'h2' });
        expect(unsaved).toEqual([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Welcome back' }]);
        expect(revision).toBe(1);

        expect(store.getState().undo()).toBe(true);
        expect(store.getState().entry).toEqual(before);
    });

    it('should undo what was typed in one field in one step, as typing in the right pane does', () => {
        const store = storeWith([block('a', { text: { en_US: 'Welcome' } })]);
        const sync = listen(store);

        sync.receive({ type: 'edit', key: 'a', field: 'text', value: 'Welcome b' });
        sync.receive({ type: 'edit', key: 'a', field: 'text', value: 'Welcome ba' });
        sync.receive({ type: 'edit', key: 'a', field: 'text', value: 'Welcome back' });
        store.getState().undo();

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Welcome' } });
    });

    it('should write rich text typed in place as its document', () => {
        const store = accordionWith(doc('We ship within a day.'));

        listen(store).receive({ type: 'edit', key: 'item', field: 'content', value: doc('We ship within a day.', ['bold']) });

        expect(store.getState().unsaved).toEqual([{ op: 'set_value', key: 'item', field: 'content', locale: 'en_US', value: doc('We ship within a day.', ['bold']) }]);
        expect(store.getState().entry.tree.blocks[0]?.children[0]?.fields['content']).toEqual({ en_US: doc('We ship within a day.', ['bold']) });
    });

    it('should remove the value of a field emptied in place', () => {
        const store = accordionWith(doc('We ship within a day.'));
        const sync = listen(store);

        sync.receive({ type: 'edit', key: 'item', field: 'content', value: null });
        sync.receive({ type: 'edit', key: 'item', field: 'title', value: '' });

        expect(store.getState().entry.tree.blocks[0]?.children[0]?.fields).toEqual({});
    });

    it.each([
        ['a field of a type that is not typed in place', { key: 'a', field: 'level', value: 'h3' }],
        ['a field the block type does not have', { key: 'a', field: 'subtitle', value: 'Hello' }],
        ['a block that is gone', { key: 'gone', field: 'text', value: 'Hello' }],
        ['text that is not a string', { key: 'a', field: 'text', value: doc('Hello') }],
    ])('should ignore an edit to %s', (_case, edit) => {
        const store = storeWith([block('a', { text: { en_US: 'Welcome' }, level: 'h2' })]);

        listen(store).receive({ type: 'edit', ...edit });

        expect(store.getState().entry.tree.blocks[0]?.fields).toEqual({ text: { en_US: 'Welcome' }, level: 'h2' });
        expect(store.getState().history.done).toHaveLength(0);
    });

    it('should ignore rich text that is not a document', () => {
        const store = accordionWith(doc('We ship within a day.'));

        listen(store).receive({ type: 'edit', key: 'item', field: 'content', value: 'We ship within a day.' });

        expect(store.getState().history.done).toHaveLength(0);
    });
});
