/**
 * What each spot in the block tree may hold. The registry resolves the rules ('*', parents) per block type
 * and per content type; these functions apply them to the working copy. Pure functions, with no React.
 */
import { missingContext } from './hostPageContext';
import { findBlock, listOf, locateBlock, topmostBlocks } from './tree';
import type { AllowedBlockType, BlockTypeDefinition, StoredBlock } from './types';

export interface NestingRules {
    blockTypes: ReadonlyMap<string, BlockTypeDefinition>;
    /** What the top level of the entry's tree may hold. */
    topLevel: readonly AllowedBlockType[];
    /**
     * The context keys the entry's content type provides its tree, or null for a snippet, which declares them, or
     * undefined for the entry itself.
     */
    provides?: readonly string[] | null | undefined;
}

/** A block type a spot allows that the picker leaves out, because the entry doesn't provide these context keys. */
export interface LeftOut {
    blockType: BlockTypeDefinition;
    missing: string[];
}

/** Where a dragged block is dropped, relative to the block under the pointer. */
export type DropZone = 'before' | 'after' | 'inside';

export interface Placement {
    /** The key of the block whose children the block joins, or null for the top level. */
    parent: string | null;
    /** Its position in that list once it's taken out of where it was. */
    index: number;
}

export function createNestingRules(blockTypes: readonly BlockTypeDefinition[], topLevel: readonly AllowedBlockType[] | null, provides?: readonly string[] | null): NestingRules {
    return { blockTypes: new Map(blockTypes.map((blockType) => [blockType.code, blockType])), topLevel: topLevel ?? [], provides };
}

/** What a spot allows: the top level when parent is null, and otherwise what the parent's block type allows. */
export function allowedIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null): readonly AllowedBlockType[] {
    if (parent === null) {
        return rules.topLevel;
    }

    const type = findBlock(tree, parent)?.type;

    return (type === undefined ? undefined : rules.blockTypes.get(type)?.children) ?? [];
}

/**
 * The block types the picker offers at a spot: allowed there, not disabled, not yet at their max, and, when the context
 * keys the entry provides are given, needing no others.
 */
export function insertableIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null, provided?: readonly string[]): BlockTypeDefinition[] {
    return fittingIn(rules, tree, parent).filter((blockType) => provided === undefined || missingContext(blockType.needs, provided).length === 0);
}

/** The block types the picker would offer at a spot but for the context keys they need that the entry doesn't provide. */
export function leftOutIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null, provided: readonly string[]): LeftOut[] {
    return fittingIn(rules, tree, parent).flatMap((blockType) => {
        const missing = missingContext(blockType.needs, provided);

        return missing.length === 0 ? [] : [{ blockType, missing }];
    });
}

/** The block types a spot allows that aren't disabled and aren't yet at their max. */
function fittingIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null): BlockTypeDefinition[] {
    const list = blocksIn(tree, parent);

    return allowedIn(rules, tree, parent).flatMap((allowed) => {
        const blockType = rules.blockTypes.get(allowed.type);

        return blockType === undefined || blockType.disabled || isFull(allowed, list) ? [] : [blockType];
    });
}

/**
 * Whether a block may move to a spot: the spot allows its block type and has room for it, the block isn't
 * moved into itself, and the spot it leaves keeps the fewest of its block type it needs.
 */
export function canMove(rules: NestingRules, tree: readonly StoredBlock[], key: string, parent: string | null): boolean {
    return canMoveBlocks(rules, tree, [key], parent);
}

/**
 * Whether blocks may move to a spot together: the spot allows each one's block type and has room for all of them, none
 * is moved into itself or into another of them, and each spot they leave keeps the fewest of each block type it needs.
 * A block inside another of them moves with that one, so it counts once.
 */
export function canMoveBlocks(rules: NestingRules, tree: readonly StoredBlock[], keys: readonly string[], parent: string | null): boolean {
    const moved = topmostBlocks(tree, keys);
    if (moved.length === 0 || keys.some((key) => findBlock(tree, key) === undefined)) {
        return false;
    }

    if (parent !== null && (findBlock(tree, parent) === undefined || findBlock(moved, parent) !== undefined)) {
        return false;
    }

    const movedKeys = new Set(moved.map((block) => block.key));
    const target = blocksIn(tree, parent).filter((sibling) => !movedKeys.has(sibling.key));
    const allowed = allowedIn(rules, tree, parent);
    const fits = [...new Set(moved.map((block) => block.type))].every((type) => {
        const rule = allowed.find((candidate) => candidate.type === type);

        return rule !== undefined && (rule.max === null || count(target, type) + count(moved, type) <= rule.max);
    });

    return fits && moved.every((block) => keepsItsMin(rules, tree, parentOf(tree, block.key), parent, block.type, moved));
}

/** Whether the spot a block of the type leaves keeps the fewest of the type it needs once the moved blocks are out of it. */
function keepsItsMin(rules: NestingRules, tree: readonly StoredBlock[], from: string | null, to: string | null, type: string, moved: readonly StoredBlock[]): boolean {
    if (from === to) {
        return true;
    }

    const min = allowedIn(rules, tree, from).find((candidate) => candidate.type === type)?.min ?? null;
    const list = blocksIn(tree, from);
    const leaving = moved.filter((block) => list.includes(block));

    return min === null || count(list, type) - count(leaving, type) >= min;
}

/**
 * Whether a copy of each of the blocks may go right after it: its spot allows its block type, with room for all the
 * copies that go there.
 */
export function canDuplicate(rules: NestingRules, tree: readonly StoredBlock[], keys: readonly string[]): boolean {
    const copies = new Map<string, number>();

    return keys.every((key) => {
        const block = findBlock(tree, key);
        const parent = parentOf(tree, key);
        const allowed = block === undefined ? undefined : allowedIn(rules, tree, parent).find((candidate) => candidate.type === block.type);
        if (block === undefined || allowed === undefined) {
            return false;
        }

        const spot = JSON.stringify([parent, block.type]);
        const copied = (copies.get(spot) ?? 0) + 1;
        copies.set(spot, copied);

        return allowed.max === null || count(blocksIn(tree, parent), block.type) + copied <= allowed.max;
    });
}

/**
 * Where a block, or several blocks dragged together, dropped on another lands, counting indexes as if the dragged
 * blocks were already taken out. A drop on one of the dragged blocks lands nowhere.
 */
export function placement(tree: readonly StoredBlock[], dragged: string | readonly string[], target: string, zone: DropZone): Placement | null {
    const draggedKeys = new Set(typeof dragged === 'string' ? [dragged] : dragged);
    const targetBlock = findBlock(tree, target);
    if (targetBlock === undefined || draggedKeys.has(target)) {
        return null;
    }

    if (zone === 'inside') {
        return { parent: target, index: targetBlock.children.filter((child) => !draggedKeys.has(child.key)).length };
    }

    const parent = parentOf(tree, target);
    const index = blocksIn(tree, parent)
        .filter((sibling) => !draggedKeys.has(sibling.key))
        .findIndex((sibling) => sibling.key === target);

    return { parent, index: zone === 'after' ? index + 1 : index };
}

/**
 * The children a new block of a block type starts with: when its block type allows exactly one block type,
 * as many of it as its min needs, and at least one, each with the children it starts with in turn.
 */
export function initialChildren(rules: NestingRules, type: string, createBlock: (type: string) => StoredBlock, seen: ReadonlySet<string> = new Set()): StoredBlock[] {
    const allowed = rules.blockTypes.get(type)?.children ?? [];
    const only = allowed.length === 1 ? allowed[0] : undefined;
    // A child type that leads back to a type being filled would never end.
    if (only === undefined || seen.has(only.type) || !rules.blockTypes.has(only.type)) {
        return [];
    }

    const amount = Math.min(Math.max(only.min ?? 0, 1), only.max ?? Number.POSITIVE_INFINITY);
    const filling = new Set([...seen, type]);

    return Array.from({ length: amount }, () => {
        const child = createBlock(only.type);
        child.children = initialChildren(rules, only.type, createBlock, filling);

        return child;
    });
}

/**
 * Whether blocks may take a block's place: its spot allows each one's block type, with room for all of them once the
 * block is out of it. Detaching a Snippet block puts its snippet's blocks in its place.
 */
export function canReplace(rules: NestingRules, tree: readonly StoredBlock[], key: string, blocks: readonly StoredBlock[]): boolean {
    if (findBlock(tree, key) === undefined) {
        return false;
    }

    const parent = parentOf(tree, key);
    const siblings = blocksIn(tree, parent).filter((sibling) => sibling.key !== key);
    const allowed = allowedIn(rules, tree, parent);

    return [...new Set(blocks.map((replacing) => replacing.type))].every((type) => {
        const rule = allowed.find((candidate) => candidate.type === type);

        return rule !== undefined && (rule.max === null || count(siblings, type) + count(blocks, type) <= rule.max);
    });
}

/** The key of the block whose children hold the block, or null when it's at the top level or missing. */
export function parentOf(tree: readonly StoredBlock[], key: string): string | null {
    return locateBlock(tree, key)?.parent ?? null;
}

function blocksIn(tree: readonly StoredBlock[], parent: string | null): readonly StoredBlock[] {
    return listOf(tree, parent) ?? [];
}

function isFull(allowed: AllowedBlockType, list: readonly StoredBlock[]): boolean {
    return allowed.max !== null && count(list, allowed.type) >= allowed.max;
}

function count(list: readonly StoredBlock[], type: string): number {
    return list.filter((block) => block.type === type).length;
}
