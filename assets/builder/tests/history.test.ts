import { enablePatches, produceWithPatches } from 'immer';
import { describe, expect, it } from 'vitest';
import { emptyHistory, rebaseHistory, recordEdit, redoEdit, undoEdit, type EditRecord, type History } from '../src/history';

enablePatches();

interface Doc {
    title: string;
    items: Array<{ id: string; text: string }>;
}

const start: Doc = { title: 'Start', items: [{ id: 'a', text: 'A' }] };

/** Makes an edit to the document, answering the document after it and its record. */
function edit(doc: Doc, recipe: (draft: Doc) => void): [Doc, EditRecord] {
    const [next, patches, inversePatches] = produceWithPatches(doc, recipe);

    return [next, { patches, inversePatches }];
}

describe('the undo history', () => {
    it('should undo the last edit and then the one before it', () => {
        const [titled, first] = edit(start, (draft) => {
            draft.title = 'Titled';
        });
        const [added, second] = edit(titled, (draft) => {
            draft.items.push({ id: 'b', text: 'B' });
        });
        const history = recordEdit(recordEdit(emptyHistory, first), second);

        const once = undoEdit(history, added);
        expect(once?.state).toEqual(titled);
        const twice = undoEdit(once?.history ?? emptyHistory, once?.state ?? added);
        expect(twice?.state).toEqual(start);
        expect(twice?.history.done).toEqual([]);
    });

    it('should answer the patches it applied, so they can be saved like any other change', () => {
        const [titled, first] = edit(start, (draft) => {
            draft.title = 'Titled';
        });

        expect(undoEdit(recordEdit(emptyHistory, first), titled)?.patches).toEqual([{ op: 'replace', path: ['title'], value: 'Start' }]);
    });

    it('should redo what it undid, in order', () => {
        const [titled, first] = edit(start, (draft) => {
            draft.title = 'Titled';
        });
        const [added, second] = edit(titled, (draft) => {
            draft.items.push({ id: 'b', text: 'B' });
        });
        let history = recordEdit(recordEdit(emptyHistory, first), second);
        let state: Doc = added;
        for (let i = 0; i < 2; i++) {
            const undone = undoEdit(history, state);
            history = undone?.history ?? history;
            state = undone?.state ?? state;
        }

        const redone = redoEdit(history, state);
        expect(redone?.state).toEqual(titled);
        expect(redone?.patches).toEqual([{ op: 'replace', path: ['title'], value: 'Titled' }]);
        expect(redoEdit(redone?.history ?? emptyHistory, redone?.state ?? state)?.state).toEqual(added);
    });

    it('should have nothing to undo or redo when it is empty', () => {
        expect(undoEdit(emptyHistory, start)).toBeNull();
        expect(redoEdit(emptyHistory, start)).toBeNull();
    });

    it('should forget what was undone once a new edit is made', () => {
        const [titled, first] = edit(start, (draft) => {
            draft.title = 'Titled';
        });
        const undone = undoEdit(recordEdit(emptyHistory, first), titled);
        const [, other] = edit(undone?.state ?? start, (draft) => {
            draft.title = 'Other';
        });

        const history = recordEdit(undone?.history ?? emptyHistory, other);

        expect(history.undone).toEqual([]);
        expect(history.done).toEqual([other]);
    });

    it('should keep edits of one group, such as typing in one field, as one step', () => {
        const [h, first] = edit(start, (draft) => {
            draft.title = 'H';
        });
        const [hi, second] = edit(h, (draft) => {
            draft.title = 'Hi';
        });
        const history = recordEdit(recordEdit(emptyHistory, first, 'title'), second, 'title');

        expect(history.done).toHaveLength(1);
        expect(undoEdit(history, hi)?.state).toEqual(start);
    });

    it('should not keep an edit of another group, or one made after an undo, with the one before', () => {
        const [h, first] = edit(start, (draft) => {
            draft.title = 'H';
        });
        const [hi, second] = edit(h, (draft) => {
            draft.title = 'Hi';
        });
        const [, third] = edit(hi, (draft) => {
            draft.items[0]!.text = 'B';
        });
        const grouped: History = recordEdit(recordEdit(emptyHistory, first, 'title'), second, 'item');
        expect(grouped.done).toHaveLength(2);

        // Typing in the title, editing an item, undoing that, then typing in the title again
        const undone = undoEdit(recordEdit(recordEdit(emptyHistory, first, 'title'), third, 'item'), start);
        expect(recordEdit(undone?.history ?? emptyHistory, second, 'title').done).toEqual([first, second]);
        expect(recordEdit(recordEdit(emptyHistory, first), second).done).toHaveLength(2);
    });
});

describe("a change that didn't come from an edit in the builder, such as a colleague's", () => {
    const items: Doc = { title: 'Start', items: [{ id: 'a', text: 'A' }, { id: 'b', text: 'B' }, { id: 'c', text: 'C' }] };

    it('should keep the edits it leaves alone undoable, and undoing them should keep the change', () => {
        const [edited, local] = edit(items, (draft) => {
            draft.items[2]!.text = 'Mine';
        });
        const [pulled, foreign] = edit(edited, (draft) => {
            draft.title = 'Theirs';
        });

        const history = rebaseHistory(recordEdit(emptyHistory, local), foreign.patches);

        expect(history.done).toEqual([local]);
        expect(undoEdit(history, pulled)?.state).toEqual({ ...items, title: 'Theirs' });
    });

    it('should drop an edit to what it changed, and the edits before that one', () => {
        const [titled, first] = edit(items, (draft) => {
            draft.title = 'Mine';
        });
        const [edited, second] = edit(titled, (draft) => {
            draft.items[1]!.text = 'Mine';
        });
        const [latest, third] = edit(edited, (draft) => {
            draft.items[2]!.text = 'Mine too';
        });
        const [, foreign] = edit(latest, (draft) => {
            draft.items[1] = { id: 'b', text: 'Theirs' };
        });

        expect(rebaseHistory(recordEdit(recordEdit(recordEdit(emptyHistory, first), second), third), foreign.patches).done).toEqual([third]);
    });

    it('should drop an edit to an item it moved to another index', () => {
        const [edited, local] = edit(items, (draft) => {
            draft.items[2]!.text = 'Mine';
        });
        const [, foreign] = edit(edited, (draft) => {
            draft.items.splice(1, 1);
        });

        expect(rebaseHistory(recordEdit(emptyHistory, local), foreign.patches).done).toEqual([]);
    });

    it('should keep an edit to an item before the index where it added one', () => {
        const [edited, local] = edit(items, (draft) => {
            draft.items[0]!.text = 'Mine';
        });
        const [pulled, foreign] = edit(edited, (draft) => {
            draft.items.push({ id: 'd', text: 'Theirs' });
        });

        const history = rebaseHistory(recordEdit(emptyHistory, local), foreign.patches);

        expect(history.done).toEqual([local]);
        expect(undoEdit(history, pulled)?.state.items.map((item) => item.text)).toEqual(['A', 'B', 'C', 'Theirs']);
    });

    it('should drop an edit that added or removed an item before one it changed', () => {
        const [added, local] = edit(items, (draft) => {
            draft.items.push({ id: 'd', text: 'Mine' });
        });
        const [, foreign] = edit(added, (draft) => {
            draft.items[3]!.text = 'Theirs';
        });

        expect(rebaseHistory(recordEdit(emptyHistory, local), foreign.patches).done).toEqual([]);
    });

    it('should keep what can be redone the same way', () => {
        const [titled, first] = edit(items, (draft) => {
            draft.title = 'Mine';
        });
        const [edited, second] = edit(titled, (draft) => {
            draft.items[1]!.text = 'Mine';
        });
        const once = undoEdit(recordEdit(recordEdit(emptyHistory, first), second), edited);
        const twice = undoEdit(once?.history ?? emptyHistory, once?.state ?? edited);
        const [, foreign] = edit(twice?.state ?? items, (draft) => {
            draft.items[1]!.text = 'Theirs';
        });

        // The title can be redone, but not the edit of the item they changed
        expect(rebaseHistory(twice?.history ?? emptyHistory, foreign.patches).undone).toEqual([first]);
    });

    it('should no longer join the next edit to the last one', () => {
        const [, first] = edit(items, (draft) => {
            draft.title = 'M';
        });
        const [, foreign] = edit(items, (draft) => {
            draft.items[0]!.text = 'Theirs';
        });

        expect(rebaseHistory(recordEdit(emptyHistory, first, 'title'), foreign.patches).group).toBeNull();
    });
});
