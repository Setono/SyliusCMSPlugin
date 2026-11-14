/**
 * The undo history of the working copy: each edit's Immer patches with their inverses. Undo applies an edit's inverse
 * patches and redo its patches again, and both answer the patches they applied, so the store saves them like any
 * other change. Pure functions, with no React and no I/O. See "Saving" in docs/spec/builder.md
 */
import { applyPatches, type Objectish, type Patch } from 'immer';

/** One edit, with what undoes it. */
export interface EditRecord {
    patches: Patch[];
    inversePatches: Patch[];
}

export interface History {
    /** The edits that can be undone, the last one last. */
    readonly done: readonly EditRecord[];
    /** The edits that were undone and can be redone, the next one last. */
    readonly undone: readonly EditRecord[];
    /** The group of the last edit, while edits of the same group still join it, such as typing in one field. */
    readonly group: string | null;
}

/** What undo or redo did: the history after it, the state after it, and the patches it applied to get there. */
export interface Step<S> {
    history: History;
    state: S;
    patches: Patch[];
}

export const emptyHistory: History = { done: [], undone: [], group: null };

/**
 * Records an edit made in the builder. It can be undone, and nothing undone before it can be redone any more. An edit
 * of the same group as the edit before it joins that one, so undo takes them back in one step.
 */
export function recordEdit(history: History, edit: EditRecord, group: string | null = null): History {
    const last = history.done.at(-1);
    if (group !== null && group === history.group && last !== undefined) {
        const joined: EditRecord = { patches: [...last.patches, ...edit.patches], inversePatches: [...edit.inversePatches, ...last.inversePatches] };

        return { done: [...history.done.slice(0, -1), joined], undone: [], group };
    }

    return { done: [...history.done, edit], undone: [], group };
}

/**
 * Takes in a change that didn't come from an edit in the builder, such as a colleague's that the server merged, whose
 * patches are already applied to the state. Edits whose patches still apply as they are stay: those that touch none
 * of what it changed, and no item of a list whose indexes it shifted. An edit it clashes with can't be undone or
 * redone any more, and nor can the edits that depend on that one: those before it, or for redo, after it.
 */
export function rebaseHistory(history: History, foreign: readonly Patch[]): History {
    const clashes = (edit: EditRecord): boolean => [...edit.patches, ...edit.inversePatches].some((patch) => foreign.some((other) => overlaps(patch, other)));
    const kept = (edits: readonly EditRecord[]): EditRecord[] => {
        const clashing = edits.map(clashes).lastIndexOf(true);

        return edits.slice(clashing + 1);
    };

    return { done: kept(history.done), undone: kept(history.undone), group: null };
}

/**
 * Whether one patch touches what the other does: one's path holds the other's, or one adds or removes an item of a
 * list at or before the index the other goes through, which shifts it.
 */
function overlaps(a: Patch, b: Patch): boolean {
    return holds(a.path, b.path) || holds(b.path, a.path) || shifts(a, b.path) || shifts(b, a.path);
}

function holds(path: Patch['path'], inner: Patch['path']): boolean {
    return path.length <= inner.length && path.every((segment, i) => String(segment) === String(inner[i]));
}

function shifts(patch: Patch, path: Patch['path']): boolean {
    const list = patch.path.slice(0, -1);
    const index = patch.path.at(-1);
    if (patch.op === 'replace' || typeof index !== 'number' || !holds(list, path) || path.length === list.length) {
        return false;
    }

    return Number(path[list.length]) >= index;
}

/** Undoes the last edit, or answers null when there's none. */
export function undoEdit<S extends Objectish>(history: History, state: S): Step<S> | null {
    const edit = history.done.at(-1);
    if (edit === undefined) {
        return null;
    }

    return {
        history: { done: history.done.slice(0, -1), undone: [...history.undone, edit], group: null },
        state: applyPatches(state, edit.inversePatches),
        patches: edit.inversePatches,
    };
}

/** Redoes the last edit undone, or answers null when there's none. */
export function redoEdit<S extends Objectish>(history: History, state: S): Step<S> | null {
    const edit = history.undone.at(-1);
    if (edit === undefined) {
        return null;
    }

    return {
        history: { done: [...history.done, edit], undone: history.undone.slice(0, -1), group: null },
        state: applyPatches(state, edit.patches),
        patches: edit.patches,
    };
}
