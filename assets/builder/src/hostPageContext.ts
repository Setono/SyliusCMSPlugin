/**
 * Host-page context: the keys, such as product, an entry provides its tree or a snippet declares, which block types and
 * condition kinds need. The builder offers only what fits, and holds a change of a snippet's declared context against
 * what depends on it, as the server does. See "Snippets and their context" in docs/spec/slots.md. Pure functions, with
 * no React.
 */
import type { SnippetPlacement } from './config';
import type { Translate } from './i18n';
import { allBlocks } from './tree';
import type { BlockTypeDefinition, ConditionKindDefinition, ContextKeyDefinition, Entry, SlotDefinition, StoredBlock } from './types';

/** The context key of an entry the CMS renders, which it provides its own tree. */
export const ENTRY = 'entry';

/** The entry field a snippet declares its context in. */
export const CONTEXT_FIELD = 'context';

/**
 * The context keys an entry provides its tree: those its content type provides, the entry itself without a word on it,
 * or for a snippet, whose content type provides null, those it declares.
 */
export function providedContext(provides: readonly string[] | null | undefined, entry: Entry): string[] {
    return provides === null ? declaredContext(entry) : [...(provides ?? [ENTRY])];
}

/** The context keys a snippet declares in its context field, none without it. */
export function declaredContext(entry: Entry): string[] {
    const value = entry.fields[CONTEXT_FIELD];

    return Array.isArray(value) ? value.filter((key): key is string => typeof key === 'string') : [];
}

/** The keys needed that aren't provided, in the order they're needed. */
export function missingContext(needs: readonly string[] | undefined, provided: readonly string[]): string[] {
    return (needs ?? []).filter((key) => !provided.includes(key));
}

/** Whether two lists hold the same context keys, in any order. */
export function sameContext(a: readonly string[], b: readonly string[]): boolean {
    return a.length === b.length && a.every((key) => b.includes(key));
}

/** Context keys by their labels, such as "Product page", and a key the code no longer declares by itself. */
export function contextNames(keys: readonly string[], contextKeys: readonly ContextKeyDefinition[], t: Translate): string[] {
    return keys.map((key) => contextKeys.find((declared) => declared.key === key)?.label ?? t('setono_sylius_cms.builder.context_removed_key', { '%key%': key }));
}

/**
 * What a snippet can declare: any page, each context key alone, then the keys each slot provides together, once each,
 * and what it declares now, even when no slot provides that, such as a key the code no longer declares.
 */
export function contextChoices(contextKeys: readonly ContextKeyDefinition[], slots: readonly SlotDefinition[], current: readonly string[]): string[][] {
    const choices: string[][] = [[]];
    const add = (keys: readonly string[]): void => {
        if (!choices.some((choice) => sameContext(choice, keys))) {
            choices.push([...keys]);
        }
    };

    contextKeys.forEach(({ key }) => add([key]));
    slots.forEach(({ provides }) => add(provides));
    add(current);

    return choices;
}

/** Something that's in the way of changing a snippet's declared context, and the keys it's in the way of. */
export type ContextBlocker =
    | { kind: 'block'; block: string; keys: string[] }
    | { kind: 'condition'; condition: string; block: string; keys: string[] }
    | { kind: 'placement'; slot: string; keys: string[] };

export interface ContextDependencies {
    blocks: readonly StoredBlock[];
    blockTypes: ReadonlyMap<string, BlockTypeDefinition>;
    conditionKinds: readonly ConditionKindDefinition[];
    /** The snippet's placements. */
    placements: readonly SnippetPlacement[];
    slots: readonly SlotDefinition[];
}

/**
 * What's in the way of changing a snippet's declared context from one list of keys to another: the blocks and the
 * conditions in its tree that need a key it removes, and its placements whose slot doesn't provide a key it adds. A
 * placement in a slot the code no longer has limits nothing. The server holds a save to the same, and more, such as
 * placements made since the builder opened.
 */
export function contextBlockers(from: readonly string[], to: readonly string[], { blocks, blockTypes, conditionKinds, placements, slots }: ContextDependencies): ContextBlocker[] {
    const removed = from.filter((key) => !to.includes(key));
    const added = to.filter((key) => !from.includes(key));
    const blockers: ContextBlocker[] = [];

    for (const block of allBlocks(blocks)) {
        const blockType = blockTypes.get(block.type);
        const needed = (blockType?.needs ?? []).filter((key) => removed.includes(key));
        if (blockType !== undefined && needed.length > 0) {
            blockers.push({ kind: 'block', block: blockType.label, keys: needed });
        }

        for (const condition of block.conditions) {
            const kind = conditionKinds.find(({ code }) => code === condition.kind);
            const neededByCondition = (kind?.needs ?? []).filter((key) => removed.includes(key));
            if (kind !== undefined && neededByCondition.length > 0) {
                blockers.push({ kind: 'condition', condition: kind.label, block: blockType?.label ?? block.type, keys: neededByCondition });
            }
        }
    }

    for (const placement of placements) {
        const slot = placement.removed ? undefined : slots.find(({ identifier }) => identifier === placement.slot);
        const unprovided = slot === undefined ? [] : missingContext(added, slot.provides);
        if (unprovided.length > 0) {
            blockers.push({ kind: 'placement', slot: placement.label, keys: unprovided });
        }
    }

    return blockers;
}

/** What's in the way, as the server words it: what it is, and the keys it's in the way of, by their labels. */
export function contextBlockerMessage(blocker: ContextBlocker, contextKeys: readonly ContextKeyDefinition[], t: Translate): string {
    const context = contextNames(blocker.keys, contextKeys, t)
        .map((name) => `"${name}"`)
        .join(', ');

    switch (blocker.kind) {
        case 'block':
            return t('setono_sylius_cms.builder.context_block_needs', { '%block%': blocker.block, '%context%': context });
        case 'condition':
            return t('setono_sylius_cms.builder.context_condition_needs', { '%kind%': blocker.condition, '%block%': blocker.block, '%context%': context });
        case 'placement':
            return t('setono_sylius_cms.builder.context_placement_does_not_provide', { '%slot%': blocker.slot, '%context%': context });
    }
}
