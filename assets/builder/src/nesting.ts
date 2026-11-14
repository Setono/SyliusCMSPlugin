/**
 * What each spot in the block tree may hold. The registry resolves the rules ('*', parents) per block type
 * and per content type; these functions apply them to the working copy. Pure functions, with no React.
 */
import { findBlock } from './tree';
import type { AllowedBlockType, BlockTypeDefinition, StoredBlock } from './types';

export interface NestingRules {
    blockTypes: ReadonlyMap<string, BlockTypeDefinition>;
    /** What the top level of the entry's tree may hold. */
    topLevel: readonly AllowedBlockType[];
}

/** Where a dragged block is dropped, relative to the block under the pointer. */
export type DropZone = 'before' | 'after' | 'inside';

export interface Placement {
    /** The key of the block whose children the block joins, or null for the top level. */
    parent: string | null;
    /** Its position in that list once it's taken out of where it was. */
    index: number;
}

export function createNestingRules(blockTypes: readonly BlockTypeDefinition[], topLevel: readonly AllowedBlockType[] | null): NestingRules {
    return { blockTypes: new Map(blockTypes.map((blockType) => [blockType.code, blockType])), topLevel: topLevel ?? [] };
}

/** What a spot allows: the top level when parent is null, and otherwise what the parent's block type allows. */
export function allowedIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null): readonly AllowedBlockType[] {
    if (parent === null) {
        return rules.topLevel;
    }

    const type = findBlock(tree, parent)?.type;

    return (type === undefined ? undefined : rules.blockTypes.get(type)?.children) ?? [];
}

/** The block types the picker offers at a spot: allowed there, not disabled, and not yet at their max. */
export function insertableIn(rules: NestingRules, tree: readonly StoredBlock[], parent: string | null): BlockTypeDefinition[] {
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
    const block = findBlock(tree, key);
    if (block === undefined || (parent !== null && (findBlock(tree, parent) === undefined || findBlock([block], parent) !== undefined))) {
        return false;
    }

    const allowed = allowedIn(rules, tree, parent).find((candidate) => candidate.type === block.type);
    const target = blocksIn(tree, parent).filter((sibling) => sibling.key !== key);
    if (allowed === undefined || isFull(allowed, target)) {
        return false;
    }

    const from = parentOf(tree, key);
    if (from === parent) {
        return true;
    }

    const min = allowedIn(rules, tree, from).find((candidate) => candidate.type === block.type)?.min ?? null;

    return min === null || count(blocksIn(tree, from), block.type) > min;
}

/** Where a block dropped on another lands, counting indexes as if the dragged block were already taken out. */
export function placement(tree: readonly StoredBlock[], dragged: string, target: string, zone: DropZone): Placement | null {
    const targetBlock = findBlock(tree, target);
    if (targetBlock === undefined) {
        return null;
    }

    if (zone === 'inside') {
        return { parent: target, index: targetBlock.children.filter((child) => child.key !== dragged).length };
    }

    const parent = parentOf(tree, target);
    const index = blocksIn(tree, parent)
        .filter((sibling) => sibling.key !== dragged)
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

/** The key of the block whose children hold the block, or null when it's at the top level or missing. */
export function parentOf(tree: readonly StoredBlock[], key: string, parent: string | null = null): string | null {
    for (const block of tree) {
        if (block.key === key) {
            return parent;
        }

        const found = parentOf(block.children, key, block.key);
        if (found !== null) {
            return found;
        }
    }

    return null;
}

function blocksIn(tree: readonly StoredBlock[], parent: string | null): readonly StoredBlock[] {
    return parent === null ? tree : (findBlock(tree, parent)?.children ?? []);
}

function isFull(allowed: AllowedBlockType, list: readonly StoredBlock[]): boolean {
    return allowed.max !== null && count(list, allowed.type) >= allowed.max;
}

function count(list: readonly StoredBlock[], type: string): number {
    return list.filter((block) => block.type === type).length;
}
