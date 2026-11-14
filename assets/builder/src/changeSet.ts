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

export type Change = AddBlockChange | RemoveBlockChange | SetValueChange;

/** Whether a field of a block type is translatable. */
export type TranslatableLookup = (blockType: string, field: string) => boolean;

type Target = { kind: 'list'; parent: string | null; depth: number } | { kind: 'fields'; key: string; field: string | undefined };

/**
 * Turns the patches of one edit into key-based changes.
 *
 * Patches into a block's fields become set_value changes. Patches that touch a list of blocks aren't
 * read index by index, because Immer reports a removal from the middle of a list as replacements plus a
 * trailing removal. Instead the list's keys are compared before and after the edit.
 */
export function changesFromPatches(patches: readonly Patch[], before: Entry, after: Entry, isTranslatable: TranslatableLookup): Change[] {
    const lists = new Map<string | null, number>();
    const fieldTargets: Array<{ key: string; field: string | undefined }> = [];

    for (const patch of patches) {
        const target = locate(patch.path, before, after);
        if (target?.kind === 'list') {
            lists.set(target.parent, target.depth);
        } else if (target?.kind === 'fields') {
            fieldTargets.push(target);
        }
    }

    const changes: Change[] = [];
    // Blocks added or removed by this edit, with everything in them. An added block carries its values.
    const addedOrRemoved = new Set<string>();

    for (const [parent] of [...lists].sort(([, a], [, b]) => a - b)) {
        if (parent !== null && addedOrRemoved.has(parent)) {
            continue;
        }

        changes.push(...diffBlockList(parent, blockList(before, parent), blockList(after, parent), isTranslatable, addedOrRemoved));
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

    return mergeChanges([], changes);
}

/**
 * Appends later changes to earlier ones, keeping only what the server still needs: the last value per
 * block, field and locale, and nothing for a block that was added and removed again in between.
 */
export function mergeChanges(earlier: readonly Change[], later: readonly Change[]): Change[] {
    return later.reduce<Change[]>((changes, change) => mergeChange(changes, change), [...earlier]);
}

function mergeChange(changes: Change[], change: Change): Change[] {
    switch (change.op) {
        case 'set_value':
            return [...changes.filter((earlier) => !isSameValue(earlier, change)), change];
        case 'add_block':
            return [...changes, change];
        case 'remove_block':
            return mergeRemoval(changes, change);
    }
}

function isSameValue(change: Change, other: SetValueChange): boolean {
    return change.op === 'set_value' && change.key === other.key && change.field === other.field && change.locale === other.locale;
}

function mergeRemoval(changes: Change[], removal: RemoveBlockChange): Change[] {
    const additionIndex = changes.findIndex((change) => change.op === 'add_block' && change.block.key === removal.key);
    const addition = changes[additionIndex];

    // A block added since the last save never reaches the server, unless a later add_block into the same
    // list counted its index with the block in place: dropping the block would then shift that one.
    if (addition?.op === 'add_block' && !hasLaterAdditionTo(changes, additionIndex, addition.parent)) {
        const gone = keysAddedWithin(changes, addition);

        return changes.filter((change) => !gone.has(change.op === 'add_block' ? change.block.key : change.key));
    }

    return [...changes.filter((change) => !(change.op === 'set_value' && change.key === removal.key)), removal];
}

function hasLaterAdditionTo(changes: readonly Change[], index: number, parent: string | null): boolean {
    return changes.slice(index + 1).some((change) => change.op === 'add_block' && change.parent === parent);
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
 * What a patch path points at: a list of blocks (the top level, or a block's children), or a block's
 * fields. Everything else, such as entry fields or a block's style, has no change of its own yet.
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

        if (property !== 'children') {
            return null;
        }

        parent = block.key;
    }
}

function blockAt(entry: Entry, parent: string | null, index: string | number | undefined): StoredBlock | undefined {
    return blockList(entry, parent)[Number(index)];
}

function diffBlockList(
    parent: string | null,
    before: readonly StoredBlock[],
    after: readonly StoredBlock[],
    isTranslatable: TranslatableLookup,
    addedOrRemoved: Set<string>,
): Change[] {
    const changes: Change[] = [];
    const remaining = new Set(after.map((block) => block.key));
    const previous = new Map(before.map((block) => [block.key, block]));

    for (const block of before) {
        if (!remaining.has(block.key)) {
            changes.push({ op: 'remove_block', key: block.key });
            collectKeys(block, addedOrRemoved);
        }
    }

    // In order, so that each index is right once the blocks before it are in place. Blocks that stay
    // keep their order: changing places needs a move, which comes with drag and drop.
    after.forEach((block, index) => {
        const old = previous.get(block.key);
        if (old === undefined) {
            changes.push({ op: 'add_block', parent, index, block });
            collectKeys(block, addedOrRemoved);
        } else if (old !== block) {
            changes.push(...diffFields(block, old.fields, isTranslatable));
            if (old.children !== block.children) {
                changes.push(...diffBlockList(block.key, old.children, block.children, isTranslatable, addedOrRemoved));
            }
        }
    });

    return changes;
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
