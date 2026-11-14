/**
 * What an edit changes in the preview: which blocks to re-render and patch, which new top-level blocks to insert and
 * which to remove, or whether only a reload of the whole page shows it. Pure, with no React and no I/O. It relies on
 * Immer's structural sharing: a block that didn't change is the same object before and after.
 * See "Updating the preview" in docs/spec/builder.md
 */
import type { Entry, StoredBlock } from './types';

export interface PreviewPlan {
    /** Only the whole page shows the change. */
    reload: boolean;
    /** Blocks in the page to re-render. */
    patch: string[];
    /** New top-level blocks, to render and insert at their spot. */
    insert: string[];
    /** Top-level blocks gone from the tree. */
    remove: string[];
}

export function planPreviewUpdate(before: Entry, after: Entry): PreviewPlan {
    const plan: PreviewPlan = { reload: false, patch: [], insert: [], remove: [] };
    if (before === after) {
        return plan;
    }

    // Entry fields, translations and the layout show around the blocks, so they need the whole page
    const { tree: treeBefore, ...restBefore } = before;
    const { tree: treeAfter, ...restAfter } = after;
    if (treeBefore.layout !== treeAfter.layout || (Object.keys(restBefore) as Array<keyof typeof restBefore>).some((key) => restBefore[key] !== restAfter[key])) {
        return { ...plan, reload: true };
    }

    const kept = keysIn(treeBefore.blocks).filter((key) => keysIn(treeAfter.blocks).includes(key));
    if (kept.join() !== keysIn(treeAfter.blocks).filter((key) => kept.includes(key)).join()) {
        return { ...plan, reload: true };
    }

    plan.remove = keysIn(treeBefore.blocks).filter((key) => !kept.includes(key));
    plan.insert = keysIn(treeAfter.blocks).filter((key) => !kept.includes(key));
    planChanges(treeBefore.blocks, treeAfter.blocks, plan.patch);

    return plan;
}

function keysIn(blocks: readonly StoredBlock[]): string[] {
    return blocks.map((block) => block.key);
}

/** Adds the blocks of a list that changed to the patch list: the block itself, or the blocks in it that changed. */
function planChanges(before: readonly StoredBlock[], after: readonly StoredBlock[], patch: string[]): void {
    const previous = new Map(before.map((block) => [block.key, block]));

    for (const block of after) {
        const old = previous.get(block.key);
        if (old === undefined || old === block) {
            continue;
        }

        const { children: oldChildren, ...oldOwn } = old;
        const { children, ...own } = block;
        const ownChanged = (Object.keys(own) as Array<keyof typeof own>).some((key) => own[key] !== oldOwn[key]);

        // A block renders its children, so a child added, removed or moved re-renders it
        if (ownChanged || keysIn(oldChildren).join() !== keysIn(children).join()) {
            patch.push(block.key);
        } else {
            planChanges(oldChildren, children, patch);
        }
    }
}
