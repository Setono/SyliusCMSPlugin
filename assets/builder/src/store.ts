/**
 * The working copy of the entry and everything the builder's panes show about it. Every edit goes
 * through Immer, whose patches build the autosave change set and whose inverse patches will give undo.
 */
import { enablePatches, produceWithPatches, type Patch } from 'immer';
import { createStore, type StoreApi } from 'zustand/vanilla';
import { changesFromPatches, mergeChanges, type Change, type TranslatableLookup } from './changeSet';
import { findBlock, generateBlockKey, removeBlockFrom } from './tree';
import type { BlockTypeDefinition, Entry, EntryStatus, JsonValue, StoredBlock } from './types';
import { writeFieldValue } from './values';

enablePatches();

/** One edit, with what undoes it. */
export interface EditRecord {
    patches: Patch[];
    inversePatches: Patch[];
}

/** idle until the first edit, then saving while there are unsaved changes, then saved, or failed. */
export type SaveState = 'idle' | 'saving' | 'saved' | 'failed';

export type PublishState = 'idle' | 'publishing' | 'failed';

export interface BuilderData {
    /** The one locale the builder edits. */
    locale: string;
    blockTypes: BlockTypeDefinition[];
    entry: Entry;
    status: EntryStatus;
    selectedKey: string | null;
    history: EditRecord[];
    /** The changes not yet sent to the server, in order. */
    unsaved: Change[];
    /** Counts the edits, so autosave can tell an edit from other state changes. */
    revision: number;
    saveState: SaveState;
    publishState: PublishState;
}

export interface BuilderActions {
    /** Appends a new block of the block type to the top level and selects it. Returns its key. */
    addBlock(blockType: string): string | null;
    removeBlock(key: string): void;
    selectBlock(key: string | null): void;
    /** Sets a field's value in the edited locale, or its one value when it's shared. null or '' removes it. */
    setFieldValue(key: string, field: string, value: JsonValue | null): void;
}

export type BuilderState = BuilderData & BuilderActions;

export type BuilderStore = StoreApi<BuilderState>;

export interface BuilderStoreOptions {
    locale: string;
    blockTypes: BlockTypeDefinition[];
    entry: Entry;
    status: EntryStatus;
    generateKey?: () => string;
}

export function createBuilderStore({ locale, blockTypes, entry, status, generateKey = generateBlockKey }: BuilderStoreOptions): BuilderStore {
    const blockTypesByCode = new Map(blockTypes.map((blockType) => [blockType.code, blockType]));
    const isTranslatable: TranslatableLookup = (blockType, field) =>
        blockTypesByCode.get(blockType)?.fields.find((definition) => definition.name === field)?.schema['x-cms-translatable'] === true;

    return createStore<BuilderState>()((set, get) => {
        /** Applies an edit to the working copy, recording its patches and the changes they make. */
        const edit = (recipe: (working: Entry) => void, extra: Partial<BuilderData> = {}): void => {
            const state = get();
            const [next, patches, inversePatches] = produceWithPatches<Entry, Entry>(state.entry, recipe);
            if (patches.length === 0) {
                if (Object.keys(extra).length > 0) {
                    set(extra);
                }

                return;
            }

            set({
                ...extra,
                entry: next,
                history: [...state.history, { patches, inversePatches }],
                unsaved: mergeChanges(state.unsaved, changesFromPatches(patches, state.entry, next, isTranslatable)),
                revision: state.revision + 1,
            });
        };

        return {
            locale,
            blockTypes,
            entry,
            status,
            selectedKey: null,
            history: [],
            unsaved: [],
            revision: 0,
            saveState: 'idle',
            publishState: 'idle',

            addBlock(code) {
                const blockType = blockTypesByCode.get(code);
                if (blockType === undefined) {
                    return null;
                }

                const block: StoredBlock = {
                    key: generateKey(),
                    type: blockType.code,
                    version: blockType.version,
                    style: null,
                    fields: {},
                    translated: {},
                    conditions: [],
                    children: [],
                };

                edit(
                    (working) => {
                        working.tree.blocks.push(block);
                    },
                    { selectedKey: block.key },
                );

                return block.key;
            },

            removeBlock(key) {
                const { selectedKey, entry: current } = get();
                const removed = findBlock(current.tree.blocks, key);
                const deselect = selectedKey !== null && removed !== undefined && findBlock([removed], selectedKey) !== undefined;

                edit(
                    (working) => {
                        removeBlockFrom(working.tree.blocks, key);
                    },
                    deselect ? { selectedKey: null } : {},
                );
            },

            selectBlock(key) {
                set({ selectedKey: key });
            },

            setFieldValue(key, field, value) {
                const block = findBlock(get().entry.tree.blocks, key);
                if (block === undefined) {
                    return;
                }

                const translatable = isTranslatable(block.type, field);
                edit((working) => {
                    const target = findBlock(working.tree.blocks, key);
                    if (target !== undefined) {
                        writeFieldValue(target.fields, field, translatable, get().locale, value);
                    }
                });
            },
        };
    });
}
