import type { Entry, StoredBlock } from './types';

interface TreeBlock<B> {
    key: string;
    children: B[];
}

/** Finds a block anywhere in the block tree by its key. */
export function findBlock<B extends TreeBlock<B>>(blocks: B[], key: string): B | undefined {
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

/** Removes a block, and with it its children, from wherever it is in the block tree. */
export function removeBlockFrom<B extends TreeBlock<B>>(blocks: B[], key: string): boolean {
    const index = blocks.findIndex((block) => block.key === key);
    if (index !== -1) {
        blocks.splice(index, 1);

        return true;
    }

    return blocks.some((block) => removeBlockFrom(block.children, key));
}

/** The blocks of one list: the top level when the parent is null, and otherwise the parent's children. */
export function blockList(entry: Entry, parent: string | null): StoredBlock[] {
    if (parent === null) {
        return entry.tree.blocks;
    }

    return findBlock(entry.tree.blocks, parent)?.children ?? [];
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
