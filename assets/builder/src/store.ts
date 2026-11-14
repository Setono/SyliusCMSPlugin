/**
 * The working copy of the entry and everything the builder's panes show about it. Every edit goes
 * through Immer, whose patches build the autosave change set and whose inverse patches give undo.
 */
import { enablePatches, produceWithPatches, type Patch } from 'immer';
import { createStore, type StoreApi } from 'zustand/vanilla';
import { changesFromPatches, mergeChanges, type Change, type TranslatableLookup } from './changeSet';
import { emptyHistory, recordEdit, redoEdit, undoEdit, type History, type Step } from './history';
import { canDuplicate, canMoveBlocks, createNestingRules, initialChildren, insertableIn, type NestingRules } from './nesting';
import { copyWithNewKeys, findBlock, generateBlockKey, locateBlock, moveBlocksTo, removeBlockFrom, topmostBlocks } from './tree';
import type { BlockTypeDefinition, ContentTypeDefinition, Entry, EntryStatus, JsonValue, StoredBlock } from './types';
import { writeFieldValue } from './values';

enablePatches();

/** idle until the first edit, then saving while there are unsaved changes, then saved, or failed. */
export type SaveState = 'idle' | 'saving' | 'saved' | 'failed';

export type PublishState = 'idle' | 'publishing' | 'failed';

export type DiscardState = 'idle' | 'discarding' | 'failed';

export interface BuilderData {
    /** The one locale the builder edits. */
    locale: string;
    blockTypes: BlockTypeDefinition[];
    /** What each spot in the tree may hold. */
    rules: NestingRules;
    entry: Entry;
    status: EntryStatus;
    /** The block the right pane and the preview's toolbar show: the one selected last, or null. */
    selectedKey: string | null;
    /** Every selected block, in the order they were selected. ⌘-click selects several. */
    selection: string[];
    /** The edits that can be undone and redone. */
    history: History;
    /** The changes not yet sent to the server, in order. */
    unsaved: Change[];
    /** Counts the edits, so autosave can tell an edit from other state changes. */
    revision: number;
    saveState: SaveState;
    publishState: PublishState;
    /** Why the server refused the last publish, such as a listener's veto, or null. */
    publishRefusal: string | null;
    discardState: DiscardState;
}

export interface BuilderActions {
    /**
     * Inserts a new block of the block type and selects it: at the top level when parent is null, otherwise into
     * the parent's children, at index or at the end. A container that allows only one block type gets those
     * children straight away. Returns its key, or null when the spot doesn't take the block type.
     */
    addBlock(blockType: string, parent?: string | null, index?: number): string | null;
    /** Moves a block, with its children, unless the nesting rules refuse it. The index counts the list once the block is taken out. */
    moveBlock(key: string, parent: string | null, index: number): boolean;
    /**
     * Moves blocks, with their children, one after the other in tree order, in one edit, unless the nesting rules
     * refuse one of them. A block inside another of them moves with that one. The index counts the list once the
     * blocks are taken out.
     */
    moveBlocks(keys: readonly string[], parent: string | null, index: number): boolean;
    /**
     * Moves each block one place up or down in its own list, past the block next to it that isn't moved too, in one
     * edit. A block inside another of them moves with that one. Returns whether any block moved.
     */
    shiftBlocks(keys: readonly string[], direction: 'up' | 'down'): boolean;
    removeBlock(key: string): void;
    /** Removes the blocks, with their children, in one edit. A block inside another of them counts once. */
    removeBlocks(keys: readonly string[]): void;
    /**
     * Puts a copy of each block right after it, with a new key for it and every block inside it, in one edit, and
     * selects the copies. A block inside another of them counts once. Returns the copies' keys in tree order, or null
     * when the nesting rules have no room for one of them.
     */
    duplicateBlocks(keys: readonly string[]): string[] | null;
    /** Selects only this block, or nothing when the key is null. */
    selectBlock(key: string | null): void;
    /** Adds a block to the selection, or takes it out when it's selected already, as ⌘-click does. */
    toggleSelection(key: string): void;
    /**
     * Sets a field's value in the edited locale, or its one value when it's shared. null or '' removes it. Values set
     * one after another in the same field, such as while typing, are undone in one step.
     */
    setFieldValue(key: string, field: string, value: JsonValue | null): void;
    /** Picks one of the block type's styles for a block, by its code, or none when the style is null. */
    setBlockStyle(key: string, style: string | null): void;
    /** Undoes the last edit, which is then saved like any other change. Returns whether there was one. */
    undo(): boolean;
    /** Redoes the last edit undone, which is then saved like any other change. Returns whether there was one. */
    redo(): boolean;
    /**
     * Loads a working copy from the server in place of the one being edited, such as the published content after
     * discarding the draft. It isn't an edit: nothing is left to save or undo.
     */
    load(entry: Entry, status: EntryStatus): void;
}

export type BuilderState = BuilderData & BuilderActions;

export type BuilderStore = StoreApi<BuilderState>;

export interface BuilderStoreOptions {
    locale: string;
    blockTypes: BlockTypeDefinition[];
    contentTypes: ContentTypeDefinition[];
    entry: Entry;
    status: EntryStatus;
    generateKey?: () => string;
}

export function createBuilderStore({ locale, blockTypes, contentTypes, entry, status, generateKey = generateBlockKey }: BuilderStoreOptions): BuilderStore {
    const blockTypesByCode = new Map(blockTypes.map((blockType) => [blockType.code, blockType]));
    const rules = createNestingRules(blockTypes, contentTypes.find((contentType) => contentType.code === entry.contentType)?.blocks ?? null);
    const createBlock = (type: string): StoredBlock => ({
        key: generateKey(),
        type,
        version: blockTypesByCode.get(type)?.version ?? 1,
        style: null,
        fields: {},
        translated: {},
        conditions: [],
        children: [],
    });
    const isTranslatable: TranslatableLookup = (blockType, field) =>
        blockTypesByCode.get(blockType)?.fields.find((definition) => definition.name === field)?.schema['x-cms-translatable'] === true;

    return createStore<BuilderState>()((set, get) => {
        /**
         * Puts a changed working copy in place, with the changes its patches make left to save. A selected block it no
         * longer has is no longer selected.
         */
        const change = (next: Entry, patches: readonly Patch[], history: History, extra: Partial<BuilderData> = {}): void => {
            const state = get();

            set({
                ...extra,
                ...selectedIn(next, extra.selection ?? state.selection, extra.selectedKey === undefined ? state.selectedKey : extra.selectedKey),
                entry: next,
                history,
                unsaved: mergeChanges(state.unsaved, changesFromPatches(patches, state.entry, next, isTranslatable)),
                revision: state.revision + 1,
            });
        };

        /**
         * Applies an edit to the working copy, recording its patches, and the changes they make. An edit of the same
         * group as the one before it is undone together with it.
         */
        const edit = (recipe: (working: Entry) => void, extra: Partial<BuilderData> = {}, group: string | null = null): void => {
            const state = get();
            const [next, patches, inversePatches] = produceWithPatches<Entry, Entry>(state.entry, recipe);
            if (patches.length === 0) {
                if (Object.keys(extra).length > 0) {
                    set(extra);
                }

                return;
            }

            change(next, patches, recordEdit(state.history, { patches, inversePatches }, group), extra);
        };

        const step = (taken: Step<Entry> | null): boolean => {
            if (taken === null) {
                return false;
            }

            change(taken.state, taken.patches, taken.history);

            return true;
        };

        return {
            locale,
            blockTypes,
            rules,
            entry,
            status,
            selectedKey: null,
            selection: [],
            history: emptyHistory,
            unsaved: [],
            revision: 0,
            saveState: 'idle',
            publishState: 'idle',
            publishRefusal: null,
            discardState: 'idle',

            addBlock(code, parent = null, index) {
                if (!insertableIn(rules, get().entry.tree.blocks, parent).some((blockType) => blockType.code === code)) {
                    return null;
                }

                const block = createBlock(code);
                block.children = initialChildren(rules, code, createBlock);

                edit(
                    (working) => {
                        const list = listIn(working, parent);
                        list?.splice(index ?? list.length, 0, block);
                    },
                    only(block.key),
                );

                return block.key;
            },

            moveBlock(key, parent, index) {
                return get().moveBlocks([key], parent, index);
            },

            moveBlocks(keys, parent, index) {
                if (!canMoveBlocks(rules, get().entry.tree.blocks, keys, parent)) {
                    return false;
                }

                edit((working) => {
                    moveBlocksTo(working.tree.blocks, keys, parent, index);
                });

                return true;
            },

            shiftBlocks(keys, direction) {
                const moved = new Set(topmostBlocks(get().entry.tree.blocks, keys).map((block) => block.key));
                let shifted = false;

                edit((working) => {
                    for (const parent of new Set([...moved].map((key) => locateBlock(working.tree.blocks, key)?.parent ?? null))) {
                        shifted = shiftWithin(listIn(working, parent) ?? [], moved, direction) || shifted;
                    }
                });

                return shifted;
            },

            removeBlock(key) {
                get().removeBlocks([key]);
            },

            removeBlocks(keys) {
                const removed = topmostBlocks(get().entry.tree.blocks, keys);

                edit((working) => {
                    removed.forEach((block) => removeBlockFrom(working.tree.blocks, block.key));
                });
            },

            duplicateBlocks(keys) {
                const originals = topmostBlocks(get().entry.tree.blocks, keys);
                if (originals.length === 0 || !canDuplicate(rules, get().entry.tree.blocks, originals.map((block) => block.key))) {
                    return null;
                }

                const copies = originals.map((original) => copyWithNewKeys(original, generateKey));
                const copied = copies.map((copy) => copy.key);

                edit(
                    (working) => {
                        copies.forEach((copy, i) => {
                            const spot = locateBlock(working.tree.blocks, originals[i]?.key ?? '');
                            if (spot !== undefined) {
                                listIn(working, spot.parent)?.splice(spot.index + 1, 0, copy);
                            }
                        });
                    },
                    { selection: copied, selectedKey: copied.at(-1) ?? null },
                );

                return copied;
            },

            selectBlock(key) {
                set(only(key));
            },

            toggleSelection(key) {
                const { selection, selectedKey } = get();
                if (selection.includes(key)) {
                    const rest = selection.filter((selected) => selected !== key);
                    set({ selection: rest, selectedKey: selectedKey === key ? (rest.at(-1) ?? null) : selectedKey });

                    return;
                }

                set({ selection: [...selection, key], selectedKey: key });
            },

            load(loaded, loadedStatus) {
                const { selectedKey, selection } = get();

                set({
                    entry: loaded,
                    status: loadedStatus,
                    ...selectedIn(loaded, selection, selectedKey),
                    history: emptyHistory,
                    unsaved: [],
                });
            },

            setFieldValue(key, field, value) {
                const block = findBlock(get().entry.tree.blocks, key);
                if (block === undefined) {
                    return;
                }

                const translatable = isTranslatable(block.type, field);
                edit(
                    (working) => {
                        const target = findBlock(working.tree.blocks, key);
                        if (target !== undefined) {
                            writeFieldValue(target.fields, field, translatable, get().locale, value);
                        }
                    },
                    {},
                    JSON.stringify(['set_value', key, field]),
                );
            },

            setBlockStyle(key, style) {
                edit((working) => {
                    const target = findBlock(working.tree.blocks, key);
                    if (target !== undefined) {
                        target.style = style;
                    }
                });
            },

            undo() {
                const { history, entry: current } = get();

                return step(undoEdit(history, current));
            },

            redo() {
                const { history, entry: current } = get();

                return step(redoEdit(history, current));
            },
        };
    });
}

/** One list of blocks of the working copy: the top level when the parent is null, and otherwise the parent's children. */
function listIn(working: Entry, parent: string | null): StoredBlock[] | undefined {
    return parent === null ? working.tree.blocks : findBlock(working.tree.blocks, parent)?.children;
}

/**
 * Moves each of the blocks in a list one place up or down, past the block next to it that isn't moved too. A block
 * at the end of the list it moves towards stays. Returns whether any block moved.
 */
function shiftWithin(list: StoredBlock[], moved: ReadonlySet<string>, direction: 'up' | 'down'): boolean {
    const order = direction === 'up' ? list.map((_, i) => i) : list.map((_, i) => list.length - 1 - i);
    let shifted = false;

    for (const [step, i] of order.entries()) {
        const neighbour = order[step - 1];
        const block = list[i];
        const other = neighbour === undefined ? undefined : list[neighbour];
        if (neighbour !== undefined && block !== undefined && other !== undefined && moved.has(block.key) && !moved.has(other.key)) {
            list[neighbour] = block;
            list[i] = other;
            shifted = true;
        }
    }

    return shifted;
}

/** The blocks acting on a block takes along: the whole selection when the block is part of it, and otherwise the block alone. */
export function actedOn(selection: readonly string[], key: string): readonly string[] {
    return selection.includes(key) ? selection : [key];
}

type Selected = Pick<BuilderData, 'selectedKey' | 'selection'>;

/** Only this block selected, or nothing. */
function only(key: string | null): Selected {
    return { selectedKey: key, selection: key === null ? [] : [key] };
}

/** The selection, without the blocks the working copy doesn't have. The block shown falls back to the one selected last. */
function selectedIn(entry: Entry, selection: readonly string[], selectedKey: string | null): Selected {
    const kept = selection.filter((key) => findBlock(entry.tree.blocks, key) !== undefined);

    return { selection: kept, selectedKey: selectedKey !== null && kept.includes(selectedKey) ? selectedKey : (kept.at(-1) ?? null) };
}
