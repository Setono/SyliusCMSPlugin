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
    /** Top-level blocks that are new or moved, to render and put at their spot. */
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

    const keysBefore = keysIn(treeBefore.blocks);
    const keysAfter = keysIn(treeAfter.blocks);
    const kept = keysAfter.filter((key) => keysBefore.includes(key));
    const staying = new Set(longestIncreasing(kept.map((key) => keysBefore.indexOf(key))).map((index) => keysBefore[index]));

    plan.remove = keysBefore.filter((key) => !keysAfter.includes(key));
    // New blocks and those that changed places with the others; the most blocks that keep their order stay put
    plan.insert = keysAfter.filter((key) => !staying.has(key));
    planChanges(treeBefore.blocks, treeAfter.blocks, plan.patch);
    plan.patch = plan.patch.filter((key) => !plan.insert.includes(key));

    return plan;
}

/** The longest run of the numbers, in their order, that only goes up. */
function longestIncreasing(numbers: readonly number[]): number[] {
    // runs[i] is the longest increasing run that ends with numbers[i]
    const runs: number[][] = [];
    numbers.forEach((number, i) => {
        const longest = runs.slice(0, i).reduce<number[]>((best, run) => ((run.at(-1) ?? Infinity) < number && run.length > best.length ? run : best), []);
        runs.push([...longest, number]);
    });

    return runs.reduce<number[]>((best, run) => (run.length > best.length ? run : best), []);
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
