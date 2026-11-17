/**
 * The Snippet block, which shows a snippet that stays the same wherever it's used: the builder marks it as shared,
 * opens its snippet in the builder and detaches it into a copy of the snippet's blocks. See "Snippet references" in
 * docs/spec/builder.md
 */
import type { StoredBlock } from './types';

/** The Snippet block's block type. */
export const SNIPPET_BLOCK = 'snippet';

/** The Snippet block's field that holds the snippet's UUID. */
const SNIPPET_FIELD = 'snippet';

/** Whether the block is a Snippet block. */
export function isSnippetBlock(block: StoredBlock): boolean {
    return block.type === SNIPPET_BLOCK;
}

/** The UUID of the snippet a Snippet block shows, or null for another block, or one that shows none yet. */
export function shownSnippet(block: StoredBlock): string | null {
    const value = isSnippetBlock(block) ? block.fields[SNIPPET_FIELD] : undefined;

    return typeof value === 'string' && value !== '' ? value : null;
}
