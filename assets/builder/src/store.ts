/**
 * The working copy of the entry and everything the builder's panes show about it. Every edit goes
 * through Immer, whose patches build the autosave change set and whose inverse patches give undo.
 */
import { enablePatches, produceWithPatches, type Patch } from 'immer';
import { createStore, type StoreApi } from 'zustand/vanilla';
import { applyChanges, changesFromPatches, mergeChanges, type Change, type TranslatableLookup } from './changeSet';
import { emptyHistory, rebaseHistory, recordEdit, redoEdit, undoEdit, type History, type Step } from './history';
import { canDuplicate, canMoveBlocks, createNestingRules, initialChildren, insertableIn, type NestingRules } from './nesting';
import { holdClashes, reconcile, splitValueClashes, withoutKeysIn, type HeldClash } from './pullIn';
import { copyWithNewKeys, findBlock, generateBlockKey, insertBlock, listOf, locateBlock, moveBlocksTo, removeBlockFrom, shiftWithin, topmostBlocks } from './tree';
import type { Locale } from './config';
import { localeOf } from './locales';
import { entryValues, sourceOfTyped, upToDate } from './translationState';
import type { BlockTypeDefinition, Clash, ConditionKindDefinition, ContentTypeDefinition, Entry, EntryStatus, FieldValues, JsonValue, StoredBlock, TranslationSource, VisibilityCondition } from './types';
import { normalizeValue, writeEntryTranslated, writeEntryValue, writeFieldValue, writeTranslated } from './values';

enablePatches();

/**
 * idle until the first edit, then saving while there are unsaved changes, then saved, or failed. stale when the server
 * can't merge a save onto what colleagues saved, so nothing saves until the working copy is loaded again.
 */
export type SaveState = 'idle' | 'saving' | 'saved' | 'failed' | 'stale';

export type PublishState = 'idle' | 'publishing' | 'failed';

export type DiscardState = 'idle' | 'discarding' | 'failed';

export interface BuilderData {
    /** The one locale the builder edits, which the locale switcher picks. */
    locale: string;
    blockTypes: BlockTypeDefinition[];
    /** The kinds of visibility conditions a block can carry. */
    conditionKinds: ConditionKindDefinition[];
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
    /** The working copy's version as of the last load or save, which the next save carries. */
    lockToken: number;
    /** Clashes with colleagues' changes, for the editor to resolve one at a time, the first one first. */
    clashes: HeldClash[];
    /** How many clashes the editor resolved since there were none, to count them as they go. */
    resolvedClashes: number;
    /**
     * Counts the changes to the working copy, made in the builder or pulled in from colleagues, so autosave and the
     * preview can tell them from other state changes. Loading a working copy doesn't count.
     */
    changeCount: number;
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
     * one after another in the same field, such as while typing, are undone in one step. A translatable value typed
     * over its fallback value, or copied from it, records that it's translated from the fallback value's locale, and
     * updating a value that has a source brings it up to date. See "Translation state" in docs/spec/content-model.md
     */
    setFieldValue(key: string, field: string, value: JsonValue | null): void;
    /** Records that a block's outdated value in the edited locale is up to date with the value it was translated from. */
    markValueUpToDate(key: string, field: string): void;
    /** Picks one of the block type's styles for a block, by its code, or none when the style is null. */
    setBlockStyle(key: string, style: string | null): void;
    /**
     * Sets a block's visibility conditions, all of them. Changes one after another to the same block's conditions, such
     * as picking several locales, are undone in one step.
     */
    setConditions(key: string, conditions: VisibilityCondition[]): void;
    /** Edits another locale. It isn't an edit to the working copy: nothing is saved or undone. */
    setLocale(locale: string): void;
    /**
     * Sets an entry field's value in the edited locale, such as the title. null or '' empties it. Setting a value in a
     * locale the entry has no translation in adds one, which starts shown before the first publish, and held back after
     * it. Values set one after another in the same field are undone in one step. Where the value is translated from is
     * recorded as for a block's value, except for a slug, which never falls back.
     */
    setEntryValue(field: string, value: JsonValue | null): void;
    /** Records that an entry field's outdated value in the edited locale is up to date with the value it was translated from. */
    markEntryValueUpToDate(field: string): void;
    /**
     * Adds a translation in the edited locale with these fields, such as the title and slug of the translation it falls
     * back to, and where they're translated from, or gives the translation it has them. A new one starts shown before
     * the first publish, and held back after it.
     */
    addTranslation(fields: FieldValues, translated?: Record<string, TranslationSource>): void;
    /** Switches the edited locale's translation shown, or holds it back. It does nothing when there's no translation. */
    setShown(shown: boolean): void;
    /** Undoes the last edit, which is then saved like any other change. Returns whether there was one. */
    undo(): boolean;
    /** Redoes the last edit undone, which is then saved like any other change. Returns whether there was one. */
    redo(): boolean;
    /**
     * Loads a working copy from the server in place of the one being edited, such as the published content after
     * discarding the draft. It isn't an edit: nothing is left to save or undo.
     */
    load(entry: Entry, status: EntryStatus, lockToken: number): void;
    /**
     * Pulls in a working copy the server merged a save onto, with colleagues' changes in it. The changes not yet saved
     * go on top, the way the server will apply them, and the undo stack keeps the edits that colleagues' changes leave
     * alone. It isn't an edit. The clashes are held for the editor, and so is a change not yet saved to a block that's gone.
     * Given base, the working copy as it was when the save that brought the merged one was sent, a value not yet saved
     * in a field and locale a colleague changed since is held as a clash too, instead of going on top of theirs.
     */
    pullIn(merged: Entry, clashes?: readonly Clash[], base?: Entry): void;
    /**
     * Resolves the first clash: keeping the editor's side is an edit like any other, and taking the colleague's
     * leaves the working copy as it is.
     */
    resolveClash(choice: 'mine' | 'theirs'): void;
}

export type BuilderState = BuilderData & BuilderActions;

export type BuilderStore = StoreApi<BuilderState>;

export interface BuilderStoreOptions {
    locale: string;
    /** The store's locales, with their fallback chains, which say where a value typed over a fallback value is translated from. */
    locales?: readonly Locale[];
    blockTypes: BlockTypeDefinition[];
    contentTypes: ContentTypeDefinition[];
    conditionKinds?: ConditionKindDefinition[];
    entry: Entry;
    status: EntryStatus;
    lockToken?: number;
    generateKey?: () => string;
}

export function createBuilderStore({ locale, locales = [], blockTypes, contentTypes, conditionKinds = [], entry, status, lockToken = 0, generateKey = generateBlockKey }: BuilderStoreOptions): BuilderStore {
    const chainOf = (code: string): string[] => localeOf(locales, code).fallbackChain;
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
                changeCount: state.changeCount + 1,
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
            conditionKinds,
            rules,
            entry,
            status,
            selectedKey: null,
            selection: [],
            history: emptyHistory,
            unsaved: [],
            lockToken,
            clashes: [],
            resolvedClashes: 0,
            changeCount: 0,
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
                        const list = listOf(working.tree.blocks, parent);
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
                        shifted = shiftWithin(listOf(working.tree.blocks, parent) ?? [], moved, direction) || shifted;
                    }
                });

                return shifted;
            },

            setLocale(next) {
                set({ locale: next });
            },

            setEntryValue(field, value) {
                const { locale: edited, status: current, entry: before } = get();
                // Slugs never fall back, so a slug typed has no fallback value it's translated from
                const chain = field === 'slug' ? [edited] : chainOf(edited);
                const source = sourceOfTyped(entryValues(before, field), before.translations[edited]?.translated[field], normalizeValue(value), chain);

                edit(
                    (working) => {
                        writeEntryValue(working, edited, field, value, current === 'never_published');
                        writeEntryTranslated(working, edited, field, source, current === 'never_published');
                    },
                    {},
                    JSON.stringify(['set_entry_value', field, edited]),
                );
            },

            markEntryValueUpToDate(field) {
                const { locale: edited, entry: before } = get();
                const current = before.translations[edited]?.translated[field];
                const source = current === undefined ? undefined : upToDate(entryValues(before, field), current);
                if (source !== undefined) {
                    edit((working) => writeEntryTranslated(working, edited, field, source, false));
                }
            },

            addTranslation(fields, translated = {}) {
                const { locale: edited, status: current } = get();
                const values = Object.fromEntries(Object.entries(fields).map(([field, value]) => [field, normalizeValue(value)]));

                edit((working) => {
                    const translation = working.translations[edited];
                    if (translation === undefined) {
                        working.translations[edited] = { shown: current === 'never_published', fields: values, translated: { ...translated } };
                    } else {
                        Object.assign(translation.fields, values);
                        Object.assign(translation.translated, translated);
                    }
                });
            },

            setShown(shown) {
                const edited = get().locale;

                edit((working) => {
                    const translation = working.translations[edited];
                    if (translation !== undefined) {
                        translation.shown = shown;
                    }
                });
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
                                listOf(working.tree.blocks, spot.parent)?.splice(spot.index + 1, 0, copy);
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

            load(loaded, loadedStatus, loadedLockToken) {
                const { selectedKey, selection } = get();

                set({
                    entry: loaded,
                    status: loadedStatus,
                    lockToken: loadedLockToken,
                    ...selectedIn(loaded, selection, selectedKey),
                    history: emptyHistory,
                    unsaved: [],
                    clashes: [],
                    resolvedClashes: 0,
                });
            },

            pullIn(merged, clashes = [], base) {
                const state = get();
                const pending = base === undefined ? { kept: state.unsaved, clashes: [] } : splitValueClashes(state.unsaved, base, merged);
                const { entry: target, applied, missing } = applyChanges(merged, pending.kept);
                const [next, patches] = produceWithPatches<Entry, Entry>(state.entry, (working) => reconcile(working, target));
                const held = holdClashes(state.entry, next, [...clashes, ...pending.clashes, ...missing.map((key): Clash => ({ kind: 'deleted_block', key }))], state.clashes);

                if (patches.length === 0) {
                    set({ unsaved: applied, clashes: held });

                    return;
                }

                set({
                    ...selectedIn(next, state.selection, state.selectedKey),
                    entry: next,
                    history: rebaseHistory(state.history, patches),
                    unsaved: applied,
                    clashes: held,
                    changeCount: state.changeCount + 1,
                });
            },

            resolveClash(choice) {
                const [clash, ...rest] = get().clashes;
                if (clash === undefined) {
                    return;
                }

                set({ clashes: rest, resolvedClashes: rest.length === 0 ? 0 : get().resolvedClashes + 1 });
                if (choice === 'theirs') {
                    return;
                }

                edit((working) => {
                    const blocks = working.tree.blocks;
                    switch (clash.kind) {
                        case 'value': {
                            const target = findBlock(blocks, clash.key);
                            if (target !== undefined) {
                                writeFieldValue(target.fields, clash.field, clash.locale !== null, clash.locale ?? '', clash.mine);
                            }
                            break;
                        }
                        case 'entry_value':
                            if (clash.locale !== null) {
                                writeEntryValue(working, clash.locale, clash.field, clash.mine, get().status === 'never_published');
                            }
                            break;
                        case 'changed_block':
                            removeBlockFrom(blocks, clash.key);
                            break;
                        case 'deleted_block':
                            if (findBlock(blocks, clash.key) === undefined) {
                                const parent = clash.parent !== null && findBlock(blocks, clash.parent) !== undefined ? clash.parent : null;
                                insertBlock(blocks, withoutKeysIn(blocks, clash.block), parent, clash.index, clash.after);
                            }
                            break;
                    }
                });
            },

            setFieldValue(key, field, value) {
                const block = findBlock(get().entry.tree.blocks, key);
                if (block === undefined) {
                    return;
                }

                const edited = get().locale;
                const translatable = isTranslatable(block.type, field);
                const source = translatable ? sourceOfTyped(block.fields[field], block.translated[field]?.[edited], normalizeValue(value), chainOf(edited)) : null;
                edit(
                    (working) => {
                        const target = findBlock(working.tree.blocks, key);
                        if (target !== undefined) {
                            writeFieldValue(target.fields, field, translatable, edited, value);
                            if (translatable) {
                                writeTranslated(target.translated, field, edited, source);
                            }
                        }
                    },
                    {},
                    JSON.stringify(['set_value', key, field]),
                );
            },

            markValueUpToDate(key, field) {
                const edited = get().locale;
                const block = findBlock(get().entry.tree.blocks, key);
                const current = block?.translated[field]?.[edited];
                const source = current === undefined ? undefined : upToDate(block?.fields[field], current);
                if (source === undefined) {
                    return;
                }

                edit((working) => {
                    const target = findBlock(working.tree.blocks, key);
                    if (target !== undefined) {
                        writeTranslated(target.translated, field, edited, source);
                    }
                });
            },

            setBlockStyle(key, style) {
                edit((working) => {
                    const target = findBlock(working.tree.blocks, key);
                    if (target !== undefined) {
                        target.style = style;
                    }
                });
            },

            setConditions(key, conditions) {
                edit(
                    (working) => {
                        const target = findBlock(working.tree.blocks, key);
                        if (target !== undefined) {
                            target.conditions = conditions;
                        }
                    },
                    {},
                    JSON.stringify(['set_conditions', key]),
                );
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
