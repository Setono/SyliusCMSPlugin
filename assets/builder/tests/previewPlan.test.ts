import { produce } from 'immer';
import { describe, expect, it } from 'vitest';
import { planPreviewUpdate } from '../src/previewPlan';
import type { Entry, StoredBlock } from '../src/types';
import { block, entry } from './fixtures';

function withChildren(key: string, children: StoredBlock[]): StoredBlock {
    return { ...block(key), type: 'group', children };
}

/** Edits the entry the way the store does, through Immer, so what doesn't change keeps its identity. */
function edit(before: Entry, recipe: (draft: Entry) => void): Entry {
    return produce(before, recipe);
}

describe('updating the preview after an edit', () => {
    it('should do nothing when nothing changed', () => {
        const before = entry([block('a')]);

        expect(planPreviewUpdate(before, before, ['title'])).toEqual({ reload: false, patch: [], insert: [], remove: [] });
    });

    it("should re-render a block whose fields changed, and only that one", () => {
        const before = entry([block('a'), block('b')]);
        const after = edit(before, (draft) => {
            draft.tree.blocks[1]!.fields.text = { en_US: 'Changed' };
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: ['b'], insert: [], remove: [] });
    });

    it('should re-render a block whose style changed', () => {
        const before = entry([block('a')]);
        const after = edit(before, (draft) => {
            draft.tree.blocks[0]!.style = 'dark';
        });

        expect(planPreviewUpdate(before, after, ['title']).patch).toEqual(['a']);
    });

    it('should insert a block added at the top level, and remove one removed from it', () => {
        const before = entry([block('a'), block('b')]);
        const after = edit(before, (draft) => {
            draft.tree.blocks.splice(0, 1);
            draft.tree.blocks.push(block('c'));
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: [], insert: ['c'], remove: ['a'] });
    });

    it('should re-render only the nested block whose fields changed', () => {
        const before = entry([withChildren('parent', [block('a'), block('b')])]);
        const after = edit(before, (draft) => {
            draft.tree.blocks[0]!.children[1]!.fields.text = { en_US: 'Changed' };
        });

        expect(planPreviewUpdate(before, after, ['title']).patch).toEqual(['b']);
    });

    it('should re-render the parent of a block added to or removed from its children, which it renders', () => {
        const before = entry([withChildren('parent', [block('a')])]);
        const added = edit(before, (draft) => {
            draft.tree.blocks[0]!.children.push(block('b'));
        });
        const removed = edit(before, (draft) => {
            draft.tree.blocks[0]!.children.splice(0, 1);
        });

        expect(planPreviewUpdate(before, added, ['title'])).toEqual({ reload: false, patch: ['parent'], insert: [], remove: [] });
        expect(planPreviewUpdate(before, removed, ['title'])).toEqual({ reload: false, patch: ['parent'], insert: [], remove: [] });
    });

    it('should re-render the parents a block moves between', () => {
        const before = entry([withChildren('left', [block('a'), block('b')]), withChildren('right', [])]);
        const after = edit(before, (draft) => {
            const [moved] = draft.tree.blocks[0]!.children.splice(0, 1);
            draft.tree.blocks[1]!.children.push(moved!);
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: ['left', 'right'], insert: [], remove: [] });
    });

    it('should re-render a parent whose children change places', () => {
        const before = entry([withChildren('parent', [block('a'), block('b')])]);
        const after = edit(before, (draft) => {
            draft.tree.blocks[0]!.children.reverse();
        });

        expect(planPreviewUpdate(before, after, ['title']).patch).toEqual(['parent']);
    });

    it('should take a block moved out of its parent from the page and put it at its spot in the top level', () => {
        const before = entry([withChildren('parent', [block('a')]), block('b')]);
        const after = edit(before, (draft) => {
            const [moved] = draft.tree.blocks[0]!.children.splice(0, 1);
            draft.tree.blocks.push(moved!);
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: ['parent'], insert: ['a'], remove: [] });
    });

    it('should take a top-level block moved into a parent out of the top level and re-render the parent', () => {
        const before = entry([withChildren('parent', []), block('a')]);
        const after = edit(before, (draft) => {
            const [moved] = draft.tree.blocks.splice(1, 1);
            draft.tree.blocks[0]!.children.push(moved!);
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: ['parent'], insert: [], remove: ['a'] });
    });

    it('should put a top-level block that moved at its new spot, leaving the others where they are', () => {
        const before = entry([block('a'), block('b'), block('c')]);
        const after = edit(before, (draft) => {
            const [moved] = draft.tree.blocks.splice(0, 1);
            draft.tree.blocks.push(moved!);
        });

        expect(planPreviewUpdate(before, after, ['title'])).toEqual({ reload: false, patch: [], insert: ['a'], remove: [] });
    });

    it('should reload when an entry field the layout shows changes, in any locale, and not for one it does not show', () => {
        const before = entry([block('a')]);
        const after = edit(before, (draft) => {
            draft.translations.en_US!.fields.title = 'Changed';
        });
        const added = edit(before, (draft) => {
            draft.translations.da_DK = { shown: true, fields: { title: 'Om os' }, translated: {} };
        });

        expect(planPreviewUpdate(before, after, ['title']).reload).toBe(true);
        expect(planPreviewUpdate(before, after, [])).toEqual({ reload: false, patch: [], insert: [], remove: [] });
        expect(planPreviewUpdate(before, added, ['title']).reload).toBe(true);
    });

    it('should reload when a content type field the layout shows changes, and not for one it does not show', () => {
        const before = entry([block('a')]);
        const after = edit(before, (draft) => {
            draft.fields.summary = 'Changed';
        });

        expect(planPreviewUpdate(before, after, ['summary']).reload).toBe(true);
        expect(planPreviewUpdate(before, after, ['title']).reload).toBe(false);
    });

    it('should reload when the slug changes, which moves the page, or a translation is shown or held back', () => {
        const before = entry([block('a')]);
        const slug = edit(before, (draft) => {
            draft.translations.en_US!.fields.slug = 'about';
        });
        const heldBack = edit(before, (draft) => {
            draft.translations.en_US!.shown = false;
        });

        expect(planPreviewUpdate(before, slug, []).reload).toBe(true);
        expect(planPreviewUpdate(before, heldBack, []).reload).toBe(true);
    });

    it('should not reload when only where a value was translated from changes', () => {
        const before = entry([block('a')]);
        const after = edit(before, (draft) => {
            draft.translations.en_US!.translated.title = { from: 'da_DK', fingerprint: 'f' };
        });

        expect(planPreviewUpdate(before, after, ['title']).reload).toBe(false);
    });

    it('should reload when the layout or a layout field changes', () => {
        const before = entry([block('a')]);
        const switched = edit(before, (draft) => {
            draft.tree.layout = { code: 'landing', version: 2, fields: {}, translated: {} };
        });
        const field = edit(before, (draft) => {
            draft.tree.layout!.fields.showTitle = false;
        });

        expect(planPreviewUpdate(before, switched, []).reload).toBe(true);
        expect(planPreviewUpdate(before, field, []).reload).toBe(true);
    });
});
