/**
 * What an edit changes in the preview: which blocks to re-render and patch, which new top-level blocks to insert and
 * which to remove, or whether only a reload of the whole page shows it. Pure, with no React and no I/O. It relies on
 * Immer's structural sharing: a block that didn't change is the same object before and after.
 * See "Updating the preview" in docs/spec/builder.md
 */
import { longestIncreasing } from './sequence';
import type { Entry, FieldValues, StoredBlock } from './types';

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

/**
 * @param shows the entry fields the layout prints, whose changes only a reload shows
 */
export function planPreviewUpdate(before: Entry, after: Entry, shows: readonly string[]): PreviewPlan {
    const plan: PreviewPlan = { reload: false, patch: [], insert: [], remove: [] };
    if (before === after) {
        return plan;
    }

    if (needsReload(before, after, shows)) {
        return { ...plan, reload: true };
    }

    const { tree: treeBefore } = before;
    const { tree: treeAfter } = after;

    const keysBefore = keysIn(treeBefore.blocks);
    const keysAfter = keysIn(treeAfter.blocks);
    const kept = keysAfter.filter((key) => keysBefore.includes(key));
    // Of the longest runs of blocks that keep their order, the first stays put, so a block moved down is the one that
    // moves. Read from the end, that run is a longest increasing one
    const fromTheEnd = longestIncreasing(kept.map((key) => -keysBefore.indexOf(key)).reverse());
    const staying = new Set([...fromTheEnd].map((i) => kept[kept.length - 1 - i]));

    plan.remove = keysBefore.filter((key) => !keysAfter.includes(key));
    // New blocks and those that changed places with the others; the most blocks that keep their order stay put
    plan.insert = keysAfter.filter((key) => !staying.has(key));
    planChanges(treeBefore.blocks, treeAfter.blocks, plan.patch);
    plan.patch = plan.patch.filter((key) => !plan.insert.includes(key));

    return plan;
}

/**
 * The layout and its fields show around the blocks, and so do the entry fields the layout shows, so they need the whole
 * page. So does a translation shown or held back, and the slug, which moves the page. Entry fields the layout doesn't
 * show, and where values were translated from, don't change the page.
 */
function needsReload(before: Entry, after: Entry, shows: readonly string[]): boolean {
    if (before.tree.layout !== after.tree.layout || changedFields(before.fields, after.fields).some((field) => shows.includes(field))) {
        return true;
    }

    for (const locale of new Set([...Object.keys(before.translations), ...Object.keys(after.translations)])) {
        const translationBefore = before.translations[locale];
        const translationAfter = after.translations[locale];
        if (translationBefore === translationAfter) {
            continue;
        }

        if (translationBefore?.shown !== translationAfter?.shown) {
            return true;
        }

        if (changedFields(translationBefore?.fields ?? {}, translationAfter?.fields ?? {}).some((field) => field === 'slug' || shows.includes(field))) {
            return true;
        }
    }

    return false;
}

function changedFields(before: FieldValues, after: FieldValues): string[] {
    return [...new Set([...Object.keys(before), ...Object.keys(after)])].filter((field) => before[field] !== after[field]);
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
