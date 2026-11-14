/**
 * The autosave change set: what the builder sends to the entry_save endpoint, built from the Immer
 * patches of each edit. Pure functions, with no React and no I/O.
 */
import type { Patch } from 'immer';
import { blockList, findBlock } from './tree';
import type { Entry, FieldValues, JsonValue, StoredBlock } from './types';
import { isJsonObject, jsonEqual, normalizeValue } from './values';

export interface AddBlockChange {
    op: 'add_block';
    /** The key of the block whose children it joins, or null for the top level. */
    parent: string | null;
    /** Its position in that list once the changes before it are applied. */
    index: number;
    block: StoredBlock;
}

export interface MoveBlockChange {
    op: 'move_block';
    key: string;
    /** The key of the block whose children it joins, or null for the top level. */
    parent: string | null;
    /** Its position in that list once the changes before it are applied and it's taken out of where it was. */
    index: number;
}

export interface RemoveBlockChange {
    op: 'remove_block';
    key: string;
}

export interface SetValueChange {
    op: 'set_value';
    key: string;
    field: string;
    /** The edited locale for a translatable field, and null for a shared one. */
    locale: string | null;
    /** null removes the value. */
    value: JsonValue | null;
}

export interface SetStyleChange {
    op: 'set_style';
    key: string;
    /** The code of one of the block type's styles, or null for none. */
    style: string | null;
}

export type Change = AddBlockChange | MoveBlockChange | RemoveBlockChange | SetValueChange | SetStyleChange;

/** Whether a field of a block type is translatable. */
export type TranslatableLookup = (blockType: string, field: string) => boolean;

type Target = { kind: 'list'; parent: string | null; depth: number } | { kind: 'fields'; key: string; field: string | undefined } | { kind: 'style'; key: string };

/**
 * Turns the patches of one edit into key-based changes.
 *
 * Patches into a block's fields become set_value changes, and into its style set_style. Patches that touch a list of blocks aren't
 * read index by index, because Immer reports a removal from the middle of a list as replacements plus a
 * trailing removal, and a move as a removal from one list and an addition to another. Instead each list's
 * keys are compared before and after the edit, against the tree as the server has it once the changes
 * before are applied, so every index is right when the server applies the changes in order.
 */
export function changesFromPatches(patches: readonly Patch[], before: Entry, after: Entry, isTranslatable: TranslatableLookup): Change[] {
    const lists = new Map<string | null, number>();
    const fieldTargets: Array<{ key: string; field: string | undefined }> = [];
    const styleTargets = new Set<string>();

    for (const patch of patches) {
        const target = locate(patch.path, before, after);
        if (target?.kind === 'list') {
            lists.set(target.parent, target.depth);
        } else if (target?.kind === 'fields') {
            fieldTargets.push(target);
        } else if (target?.kind === 'style') {
            styleTargets.add(target.key);
        }
    }

    const diff: ListDiff = {
        before,
        server: new ServerTree(before.tree.blocks),
        parents: parentsOf(after.tree.blocks),
        isTranslatable,
        addedOrRemoved: new Set(),
        done: new Set(),
    };
    const { addedOrRemoved } = diff;
    const changes: Change[] = [];

    for (const [parent] of [...lists].sort(([, a], [, b]) => a - b)) {
        if (parent !== null && addedOrRemoved.has(parent)) {
            continue;
        }

        changes.push(...diffBlockList(parent, blockList(after, parent), diff));
    }

    for (const { key, field } of fieldTargets) {
        const block = findBlock(after.tree.blocks, key);
        if (block === undefined || addedOrRemoved.has(key)) {
            continue;
        }

        const previousFields = findBlock(before.tree.blocks, key)?.fields ?? {};
        const fields = field === undefined ? undefined : [field];
        changes.push(...diffFields(block, previousFields, isTranslatable, fields));
    }

    for (const key of styleTargets) {
        const block = findBlock(after.tree.blocks, key);
        if (block !== undefined && !addedOrRemoved.has(key)) {
            changes.push(...diffStyle(block, findBlock(before.tree.blocks, key)?.style ?? null));
        }
    }

    return mergeChanges([], changes);
}

/**
 * Appends later changes to earlier ones, keeping only what the server still needs: the last value per
 * block, field and locale, the last style per block, and nothing for a block that was added and removed again in between.
 */
export function mergeChanges(earlier: readonly Change[], later: readonly Change[]): Change[] {
    return later.reduce<Change[]>((changes, change) => mergeChange(changes, change), [...earlier]);
}

function mergeChange(changes: Change[], change: Change): Change[] {
    switch (change.op) {
        case 'set_value':
            return [...changes.filter((earlier) => !isSameValue(earlier, change)), change];
        case 'set_style':
            return [...changes.filter((earlier) => !(earlier.op === 'set_style' && earlier.key === change.key)), change];
        case 'add_block':
        case 'move_block':
            return [...changes, change];
        case 'remove_block':
            return mergeRemoval(changes, change);
    }
}

function isSameValue(change: Change, other: SetValueChange): boolean {
    return change.op === 'set_value' && change.key === other.key && change.field === other.field && change.locale === other.locale;
}

function mergeRemoval(changes: Change[], removal: RemoveBlockChange): Change[] {
    const additionIndex = changes.map((change) => change.op === 'add_block' && change.block.key === removal.key).lastIndexOf(true);
    const addition = changes[additionIndex];

    // A block added since the last save never reaches the server, unless a later add_block into the same
    // list counted its index with the block in place: dropping the block would then shift that one. What
    // came before the addition stays, such as the removal of the block that undo put back.
    if (addition?.op === 'add_block' && !hasLaterAdditionTo(changes, additionIndex, addition.parent)) {
        const later = changes.slice(additionIndex);
        const gone = keysAddedWithin(later, addition);

        return [...changes.slice(0, additionIndex), ...later.filter((change) => !gone.has(change.op === 'add_block' ? change.block.key : change.key))];
    }

    return [...changes.filter((change) => !((change.op === 'set_value' || change.op === 'set_style') && change.key === removal.key)), removal];
}

/** A later addition into the same list counted its index with the block in place, and so may any later move. */
function hasLaterAdditionTo(changes: readonly Change[], index: number, parent: string | null): boolean {
    return changes.slice(index + 1).some((change) => (change.op === 'add_block' && change.parent === parent) || change.op === 'move_block');
}

/** The added block's key, the keys of the blocks in it, and those of the blocks added into it since. */
function keysAddedWithin(changes: readonly Change[], addition: AddBlockChange): Set<string> {
    const keys = new Set<string>();
    collectKeys(addition.block, keys);

    for (const change of changes) {
        if (change.op === 'add_block' && change.parent !== null && keys.has(change.parent)) {
            collectKeys(change.block, keys);
        }
    }

    return keys;
}

function collectKeys(block: StoredBlock, keys: Set<string>): void {
    keys.add(block.key);
    block.children.forEach((child) => collectKeys(child, keys));
}

/**
 * What a patch path points at: a list of blocks (the top level, or a block's children), a block's
 * fields, or its style. Everything else, such as entry fields, has no change of its own yet.
 */
function locate(path: Patch['path'], before: Entry, after: Entry): Target | null {
    if (path.length === 0 || (path.length === 1 && path[0] === 'tree')) {
        return { kind: 'list', parent: null, depth: 0 };
    }

    if (path[0] !== 'tree' || path[1] !== 'blocks') {
        return null;
    }

    let parent: string | null = null;
    // path[i] is an index in a list of blocks, and path[i + 1] a key of the block there.
    for (let i = 2; ; i += 2) {
        if (path.length <= i + 1 || path[i] === 'length') {
            return { kind: 'list', parent, depth: i };
        }

        const block: StoredBlock | undefined = blockAt(after, parent, path[i]) ?? blockAt(before, parent, path[i]);
        if (block === undefined) {
            return null;
        }

        const property = path[i + 1];
        if (property === 'fields') {
            const field = path[i + 2];

            return { kind: 'fields', key: block.key, field: field === undefined ? undefined : String(field) };
        }

        if (property === 'style') {
            return { kind: 'style', key: block.key };
        }

        if (property !== 'children') {
            return null;
        }

        parent = block.key;
    }
}

function blockAt(entry: Entry, parent: string | null, index: string | number | undefined): StoredBlock | undefined {
    return blockList(entry, parent)[Number(index)];
}

interface ListDiff {
    before: Entry;
    /** The tree as the server has it once the changes made so far are applied. */
    server: ServerTree;
    /** Every block of the tree after the edit, with the key of its parent, or null at the top level. */
    parents: ReadonlyMap<string, string | null>;
    isTranslatable: TranslatableLookup;
    /** Blocks added or removed by this edit, with everything in them. An added block carries its values. */
    addedOrRemoved: Set<string>;
    /** The lists already diffed, by the key of their parent. */
    done: Set<string | null>;
}

/**
 * The changes that make the server's list match the list after the edit. Blocks still in the server's list
 * that belong to another list after the edit are left where they are: they move when that list is diffed.
 */
function diffBlockList(parent: string | null, after: readonly StoredBlock[], diff: ListDiff): Change[] {
    if (diff.done.has(parent)) {
        return [];
    }

    diff.done.add(parent);
    const changes: Change[] = [];

    for (const key of diff.server.list(parent)) {
        if (!diff.parents.has(key)) {
            changes.push({ op: 'remove_block', key });
            diff.server.remove(key);

            const removed = findBlock(diff.before.tree.blocks, key);
            if (removed !== undefined) {
                collectKeys(removed, diff.addedOrRemoved);
            }
        }
    }

    // The blocks that keep their place: the most of those the server has here already that are in the right order.
    const positions = new Map(after.map((block, position) => [block.key, position]));
    const here = diff.server.list(parent).filter((key) => positions.has(key));
    const kept = new Set([...longestIncreasing(here.map((key) => positions.get(key) ?? 0))].map((i) => here[i]));

    // In order, so that each block can go right after the one before it, which is in place by then.
    after.forEach((block, position) => {
        if (!kept.has(block.key)) {
            // Blocks still to move away to other lists are counted, because the server still has them here.
            const list = diff.server.list(parent).filter((key) => key !== block.key);
            const index = position === 0 ? 0 : list.indexOf(after[position - 1]?.key ?? '') + 1;

            if (!diff.server.has(block.key)) {
                changes.push({ op: 'add_block', parent, index, block });
                diff.server.add(block, parent, index);
                collectKeys(block, diff.addedOrRemoved);

                return;
            }

            changes.push({ op: 'move_block', key: block.key, parent, index });
            diff.server.move(block.key, parent, index);
        }

        const old = findBlock(diff.before.tree.blocks, block.key);
        if (old !== undefined && old !== block) {
            changes.push(...diffFields(block, old.fields, diff.isTranslatable));
            changes.push(...diffStyle(block, old.style));
            if (old.children !== block.children) {
                changes.push(...diffBlockList(block.key, block.children, diff));
            }
        }
    });

    return changes;
}

/** The server's lists of block keys by the key of their parent, kept in step with the changes as they're made. */
class ServerTree {
    private readonly lists = new Map<string | null, string[]>();
    private readonly parents = new Map<string, string | null>();

    constructor(blocks: readonly StoredBlock[]) {
        this.addList(null, blocks);
    }

    has(key: string): boolean {
        return this.parents.has(key);
    }

    /** A copy of one list of keys. */
    list(parent: string | null): string[] {
        return [...(this.lists.get(parent) ?? [])];
    }

    add(block: StoredBlock, parent: string | null, index: number): void {
        this.insert(block.key, parent, index);
        this.addList(block.key, block.children);
    }

    move(key: string, parent: string | null, index: number): void {
        this.takeOut(key);
        this.insert(key, parent, index);
    }

    remove(key: string): void {
        this.takeOut(key);
        this.forget(key);
    }

    private addList(parent: string | null, blocks: readonly StoredBlock[]): void {
        this.lists.set(
            parent,
            blocks.map((block) => block.key),
        );

        for (const block of blocks) {
            this.parents.set(block.key, parent);
            this.addList(block.key, block.children);
        }
    }

    private insert(key: string, parent: string | null, index: number): void {
        const list = this.lists.get(parent) ?? [];
        list.splice(index, 0, key);
        this.lists.set(parent, list);
        this.parents.set(key, parent);
    }

    private takeOut(key: string): void {
        const list = this.lists.get(this.parents.get(key) ?? null) ?? [];
        list.splice(list.indexOf(key), 1);
    }

    private forget(key: string): void {
        this.parents.delete(key);
        for (const child of this.lists.get(key) ?? []) {
            this.forget(child);
        }

        this.lists.delete(key);
    }
}

/** The indexes of a longest strictly increasing subsequence of the values. */
function longestIncreasing(values: readonly number[]): Set<number> {
    // tails[n] is the index of the smallest value that ends an increasing subsequence of length n + 1.
    const tails: number[] = [];
    const previous: number[] = [];

    values.forEach((value, i) => {
        let low = 0;
        let high = tails.length;
        while (low < high) {
            const middle = (low + high) >> 1;
            if ((values[tails[middle] ?? 0] ?? 0) < value) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        previous[i] = low > 0 ? (tails[low - 1] ?? -1) : -1;
        tails[low] = i;
    });

    const indexes = new Set<number>();
    for (let i = tails[tails.length - 1] ?? -1; i !== -1; i = previous[i] ?? -1) {
        indexes.add(i);
    }

    return indexes;
}

function parentsOf(blocks: readonly StoredBlock[], parent: string | null = null, parents = new Map<string, string | null>()): Map<string, string | null> {
    for (const block of blocks) {
        parents.set(block.key, parent);
        parentsOf(block.children, block.key, parents);
    }

    return parents;
}

/** A set_style change when the block's style differs from before. */
function diffStyle(block: StoredBlock, before: string | null): SetStyleChange[] {
    return block.style === before ? [] : [{ op: 'set_style', key: block.key, style: block.style }];
}

/** set_value changes for the given fields of a block, or for all of them, that differ from before. */
function diffFields(block: StoredBlock, previousFields: FieldValues, isTranslatable: TranslatableLookup, fields?: string[]): Change[] {
    const names = fields ?? [...new Set([...Object.keys(previousFields), ...Object.keys(block.fields)])];

    return names.flatMap((field) => diffField(block.key, field, isTranslatable(block.type, field), previousFields[field], block.fields[field]));
}

function diffField(key: string, field: string, translatable: boolean, before: JsonValue | undefined, after: JsonValue | undefined): SetValueChange[] {
    if (!translatable) {
        return jsonEqual(before, after) ? [] : [{ op: 'set_value', key, field, locale: null, value: normalizeValue(after) }];
    }

    const beforeValues = isJsonObject(before) ? before : {};
    const afterValues = isJsonObject(after) ? after : {};
    const locales = new Set([...Object.keys(beforeValues), ...Object.keys(afterValues)]);

    return [...locales]
        .filter((locale) => !jsonEqual(beforeValues[locale], afterValues[locale]))
        .map((locale) => ({ op: 'set_value', key, field, locale, value: normalizeValue(afterValues[locale]) }));
}
