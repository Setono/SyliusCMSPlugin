/**
 * Pulling a colleague's changes into the working copy: the working copy becomes what the server merged, with the
 * changes not yet saved on top, and the clashes are held for the editor to resolve one at a time. Pure functions, with
 * no React and no I/O. See "Saving" in docs/spec/builder.md
 */
import type { Change, SetEntryValueChange, SetValueChange } from './changeSet';
import { blockList, findBlock, locateBlock, pathTo } from './tree';
import type { Clash, Entry, FieldValues, JsonValue, StoredBlock } from './types';
import { isJsonObject, jsonEqual, normalizeValue, readFieldValue } from './values';

/** A clash, with what keeping the editor's side needs. */
export type HeldClash =
    | Extract<Clash, { kind: 'value' | 'entry_value' | 'changed_block' }>
    | {
          kind: 'deleted_block';
          /** The deleted block that holds what the editor changed: the topmost one the working copy no longer has. */
          key: string;
          /** The block as the editor had it, with everything in it, to put back. */
          block: StoredBlock;
          /** Where it was: its parent's key, or null at the top level, its index, and the block before it. */
          parent: string | null;
          index: number;
          after?: string;
      };

/**
 * Changes a draft of the working copy into the target, by key, touching only what differs, so that Immer's patches
 * say what changed and the undo stack keeps the edits they leave alone.
 */
export function reconcile(draft: Entry, target: Entry): void {
    if (!same(draft.fields, target.fields)) {
        draft.fields = target.fields;
    }

    if (!same(draft.translations, target.translations)) {
        draft.translations = target.translations;
    }

    if (!same(draft.tree.layout, target.tree.layout)) {
        draft.tree.layout = target.tree.layout;
    }

    const pool = new Map<string, StoredBlock>();
    collect(draft.tree.blocks, pool);
    reconcileList(draft.tree.blocks, target.tree.blocks, pool);
}

/** Makes a list hold the target's blocks in its order, reusing the blocks the working copy has wherever they are. */
function reconcileList(list: StoredBlock[], target: readonly StoredBlock[], pool: ReadonlyMap<string, StoredBlock>): void {
    const blocks = target.map((wanted) => {
        const existing = pool.get(wanted.key);
        if (existing === undefined || existing.type !== wanted.type) {
            return wanted;
        }

        reconcileBlock(existing, wanted, pool);

        return existing;
    });

    if (blocks.length !== list.length || blocks.some((block, i) => block !== list[i])) {
        list.splice(0, list.length, ...blocks);
    }
}

function reconcileBlock(block: StoredBlock, target: StoredBlock, pool: ReadonlyMap<string, StoredBlock>): void {
    if (block.version !== target.version) {
        block.version = target.version;
    }

    if (block.style !== target.style) {
        block.style = target.style;
    }

    reconcileValues(block.fields, target.fields);

    if (!same(block.translated, target.translated)) {
        block.translated = target.translated;
    }

    if (!same(block.conditions, target.conditions)) {
        block.conditions = target.conditions;
    }

    reconcileList(block.children, target.children, pool);
}

/** Field values by field, and a translatable field's values by locale. */
function reconcileValues(values: FieldValues, target: FieldValues): void {
    for (const field of Object.keys(values)) {
        if (!(field in target)) {
            delete values[field];
        }
    }

    for (const [field, value] of Object.entries(target)) {
        const current = values[field];
        if (jsonEqual(current, value)) {
            continue;
        }

        if (isJsonObject(current) && isJsonObject(value)) {
            reconcileValues(current, value);
        } else {
            values[field] = value;
        }
    }
}

function collect(blocks: readonly StoredBlock[], pool: Map<string, StoredBlock>): void {
    for (const block of blocks) {
        pool.set(block.key, block);
        collect(block.children, pool);
    }
}

function same(a: unknown, b: unknown): boolean {
    return jsonEqual(a as JsonValue, b as JsonValue);
}

/**
 * The clashes to hold once the working copy has changed from before to after. Keeping the editor's side of an edit to
 * a deleted block puts back the topmost block the working copy lost, as the editor had it, so edits to several blocks
 * inside it make one clash. A clash over a block that's gone, or that's held already, is left out.
 */
export function holdClashes(before: Entry, after: Entry, clashes: readonly Clash[], held: readonly HeldClash[] = []): HeldClash[] {
    const holding = [...held];
    const has = (key: string): boolean => findBlock(after.tree.blocks, key) !== undefined;

    for (const clash of clashes) {
        // The entry is always there
        if (clash.kind === 'entry_value') {
            holding.push(clash);

            continue;
        }

        if (clash.kind === 'value' || clash.kind === 'changed_block') {
            if (has(clash.key)) {
                holding.push(clash);
            }

            continue;
        }

        const lost = pathTo(before.tree.blocks, clash.key).find((block) => !has(block.key));
        const spot = lost === undefined ? undefined : locateBlock(before.tree.blocks, lost.key);
        if (lost === undefined || spot === undefined || holding.some((other) => other.kind === 'deleted_block' && other.key === lost.key)) {
            continue;
        }

        const previous = spot.index > 0 ? blockList(before, spot.parent)[spot.index - 1]?.key : undefined;
        holding.push({ kind: 'deleted_block', key: lost.key, block: lost, parent: spot.parent, index: spot.index, ...(previous === undefined ? {} : { after: previous }) });
    }

    return holding;
}

/**
 * Splits the changes not yet saved into those that go on top of a working copy the server merged, and the value
 * clashes among them: a value set in a field and locale, of a block or of the entry, that a colleague changed to
 * something else since base, the working copy as it was when the save that brought their changes was sent. Those are
 * the edits made while that save was in flight, which the server never saw, so it couldn't find the clash itself.
 */
export function splitValueClashes(unsaved: readonly Change[], base: Entry, merged: Entry): { kept: Change[]; clashes: Clash[] } {
    const kept: Change[] = [];
    const clashes: Clash[] = [];

    for (const change of unsaved) {
        if (change.op === 'set_entry_value') {
            const theirs = entryValueIn(merged, change);
            if (jsonEqual(theirs, entryValueIn(base, change)) || jsonEqual(theirs, change.value)) {
                kept.push(change);
            } else {
                clashes.push({ kind: 'entry_value', field: change.field, locale: change.locale, mine: change.value, theirs });
            }

            continue;
        }

        const theirs = change.op === 'set_value' ? valueIn(merged, change) : undefined;
        if (change.op !== 'set_value' || theirs === undefined || jsonEqual(theirs, valueIn(base, change) ?? null) || jsonEqual(theirs, change.value)) {
            kept.push(change);

            continue;
        }

        clashes.push({ kind: 'value', key: change.key, field: change.field, locale: change.locale, mine: change.value, theirs });
    }

    return { kept, clashes };
}

/** The value a set_value change sets, as a working copy has it, or undefined when the working copy hasn't the block. */
function valueIn(entry: Entry, change: SetValueChange): JsonValue | null | undefined {
    const block = findBlock(entry.tree.blocks, change.key);

    return block === undefined ? undefined : normalizeValue(readFieldValue(block.fields, change.field, change.locale !== null, change.locale ?? ''));
}

/** The value a set_entry_value change sets, as a working copy has it: null when it has none. */
function entryValueIn(entry: Entry, change: SetEntryValueChange): JsonValue | null {
    return change.locale === null ? null : normalizeValue(entry.translations[change.locale]?.fields[change.field]);
}

/** A block to put back, without the blocks inside it that the working copy has elsewhere by now. */
export function withoutKeysIn(blocks: readonly StoredBlock[], block: StoredBlock): StoredBlock {
    return {
        ...block,
        children: block.children.filter((child) => findBlock(blocks, child.key) === undefined).map((child) => withoutKeysIn(blocks, child)),
    };
}
