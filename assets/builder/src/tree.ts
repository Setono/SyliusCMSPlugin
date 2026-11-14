import type { Entry, StoredBlock } from './types';

interface TreeBlock<B> {
    key: string;
    children: B[];
}

/** A spot in the block tree: a position in the top level when the parent is null, and otherwise in the parent's children. */
export interface BlockSpot {
    parent: string | null;
    index: number;
}

/** Finds a block anywhere in the block tree by its key. */
export function findBlock<B extends TreeBlock<B>>(blocks: readonly B[], key: string): B | undefined {
    for (const block of blocks) {
        if (block.key === key) {
            return block;
        }

        const child = findBlock(block.children, key);
        if (child !== undefined) {
            return child;
        }
    }

    return undefined;
}

/** Where a block is in the block tree: its parent's key, or null at the top level, and its index there. */
export function locateBlock<B extends TreeBlock<B>>(blocks: B[], key: string, parent: string | null = null): BlockSpot | undefined {
    const index = blocks.findIndex((block) => block.key === key);
    if (index !== -1) {
        return { parent, index };
    }

    for (const block of blocks) {
        const spot = locateBlock(block.children, key, block.key);
        if (spot !== undefined) {
            return spot;
        }
    }

    return undefined;
}

/** Removes a block, and with it its children, from wherever it is in the block tree. */
export function removeBlockFrom<B extends TreeBlock<B>>(blocks: B[], key: string): boolean {
    const index = blocks.findIndex((block) => block.key === key);
    if (index !== -1) {
        blocks.splice(index, 1);

        return true;
    }

    return blocks.some((block) => removeBlockFrom(block.children, key));
}

/**
 * Moves blocks, with their children, to a list, one after the other in tree order: the top level when parent is null,
 * and otherwise the parent's children. A block inside another of them moves with that one. The index counts the list
 * once the blocks are taken out, and is clamped to it.
 */
export function moveBlocksTo<B extends TreeBlock<B>>(blocks: B[], keys: readonly string[], parent: string | null, index: number): boolean {
    const moved = topmostBlocks(blocks, keys);
    const target = parent === null ? blocks : findBlock(blocks, parent)?.children;
    if (moved.length === 0 || target === undefined || findBlock(moved, parent ?? '') !== undefined) {
        return false;
    }

    moved.forEach((block) => removeBlockFrom(blocks, block.key));
    target.splice(Math.max(0, Math.min(index, target.length)), 0, ...moved);

    return true;
}

/** The blocks of one list: the top level when the parent is null, and otherwise the parent's children. */
export function blockList(entry: Entry, parent: string | null): StoredBlock[] {
    if (parent === null) {
        return entry.tree.blocks;
    }

    return findBlock(entry.tree.blocks, parent)?.children ?? [];
}

/**
 * The blocks among the keys that no other block among them holds, in tree order, so that a selected block inside
 * another selected block counts once. Keys the tree doesn't have are left out.
 */
export function topmostBlocks<B extends TreeBlock<B>>(blocks: readonly B[], keys: readonly string[]): B[] {
    const wanted = new Set(keys);

    return blocks.flatMap((block) => (wanted.has(block.key) ? [block] : topmostBlocks(block.children, keys)));
}

/** The blocks from the top level down to the block, the block last, or nothing when the tree doesn't have it. */
export function pathTo<B extends TreeBlock<B>>(blocks: readonly B[], key: string): B[] {
    for (const block of blocks) {
        if (block.key === key) {
            return [block];
        }

        const below = pathTo(block.children, key);
        if (below.length > 0) {
            return [block, ...below];
        }
    }

    return [];
}

/**
 * Whether moving the blocks one place up or down moves any of them: one of them has a block next to it in that
 * direction, in its list, that doesn't move too. A block inside another of them moves with that one.
 */
export function canShift<B extends TreeBlock<B>>(blocks: readonly B[], keys: readonly string[], direction: 'up' | 'down'): boolean {
    const moved = new Set(topmostBlocks(blocks, keys).map((block) => block.key));
    const step = direction === 'up' ? -1 : 1;
    const shifts = (list: readonly B[]): boolean =>
        list.some((block, i) => {
            const neighbour = list[i + step];

            return (moved.has(block.key) && neighbour !== undefined && !moved.has(neighbour.key)) || shifts(block.children);
        });

    return shifts(blocks);
}

/** A copy of a block with a new key for it and for every block inside it. */
export function copyWithNewKeys(block: StoredBlock, generateKey: () => string): StoredBlock {
    const key = generateKey();

    return { ...block, key, children: block.children.map((child) => copyWithNewKeys(child, generateKey)) };
}

/** A new lowercase UUID for a block key. */
export function generateBlockKey(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // crypto.randomUUID() needs a secure context, and an admin may be served over plain HTTP.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = ((bytes[6] ?? 0) & 0x0f) | 0x40;
    bytes[8] = ((bytes[8] ?? 0) & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
