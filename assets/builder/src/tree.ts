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
export function locateBlock<B extends TreeBlock<B>>(blocks: readonly B[], key: string, parent: string | null = null): BlockSpot | undefined {
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

/**
 * One list of blocks: the top level when the parent is null, and otherwise the parent's children, or undefined when
 * the tree hasn't the parent.
 */
export function listOf<B extends TreeBlock<B>>(blocks: B[], parent: string | null): B[] | undefined;
export function listOf<B extends TreeBlock<B>>(blocks: readonly B[], parent: string | null): readonly B[] | undefined;
export function listOf<B extends TreeBlock<B>>(blocks: readonly B[], parent: string | null): readonly B[] | undefined {
    return parent === null ? blocks : findBlock(blocks, parent)?.children;
}

/** The blocks of one list of the entry's tree: the top level when the parent is null, and otherwise the parent's children. */
export function blockList(entry: Entry, parent: string | null): StoredBlock[] {
    return listOf(entry.tree.blocks, parent) ?? [];
}

/** Inserts a block right after the block it names when the list has that block, and otherwise at its index. */
export function insertBlock(blocks: StoredBlock[], block: StoredBlock, parent: string | null, index: number, after?: string): void {
    const list = listOf(blocks, parent) ?? [];
    const anchor = after === undefined ? -1 : list.findIndex((sibling) => sibling.key === after);

    list.splice(anchor === -1 ? Math.min(Math.max(index, 0), list.length) : anchor + 1, 0, block);
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
    // Shifting a copy of each list tells whether shifting the list moves anything
    const shifts = (list: readonly B[]): boolean => shiftWithin([...list], moved, direction) || list.some((block) => shifts(block.children));

    return shifts(blocks);
}

/**
 * Moves each of the blocks in a list one place up or down, past the block next to it that isn't moved too. A block
 * at the end of the list it moves towards stays. Returns whether any block moved.
 */
export function shiftWithin<B extends TreeBlock<B>>(list: B[], moved: ReadonlySet<string>, direction: 'up' | 'down'): boolean {
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
