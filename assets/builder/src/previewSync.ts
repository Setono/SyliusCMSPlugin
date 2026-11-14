/**
 * Keeps the preview in step with the working copy, through the bridge in the preview's page. After each edit it has
 * the changed blocks rendered by preview_render and patched into the page, so the page doesn't reload. It links the
 * selection both ways, and turns what the editor does in the preview, such as typing in place, into edits. See
 * "The bridge", "Working in the preview" and "Updating the preview" in docs/spec/builder.md
 */
import type { BridgeStrings, FromBridge, RenderedBlock, ToBridge } from './bridge/protocol';
import { planPreviewUpdate, type PreviewPlan } from './previewPlan';
import type { BuilderStore } from './store';
import { blockList, findBlock, locateBlock, type BlockSpot } from './tree';
import type { JsonValue, StoredBlock } from './types';
import { isJsonObject } from './values';

export interface PreviewSyncOptions {
    store: BuilderStore;
    strings: BridgeStrings;
    /** Renders blocks of the working copy under the preview token, answering their HTML by key. */
    render(token: string, blocks: StoredBlock[]): Promise<Record<string, string>>;
    /** Loads the page anew, for a change the bridge can't patch in. */
    reload(): void;
    /** Opens the block picker for a spot, next to where the editor clicked + in the preview's viewport. */
    openBlockPicker(spot: BlockSpot, at: { x: number; y: number }): void;
    /** Takes the editor to the selected block's settings in the right pane. */
    openSettings(): void;
}

export interface PreviewSync {
    /** The bridge in a newly loaded page is ready: it gets what it needs, and the changes made meanwhile. */
    connect(post: (message: ToBridge) => void, token: string): void;
    /** The page is going away, so nothing more is sent to it. */
    disconnect(): void;
    receive(message: FromBridge): void;
    dispose(): void;
}

export function createPreviewSync({ store, strings, render, reload, openBlockPicker, openSettings }: PreviewSyncOptions): PreviewSync {
    let bridge: { post: (message: ToBridge) => void; token: string } | null = null;
    // Counts the pages, so an answer for a page that's gone is dropped
    let page = 0;
    let rendering = false;
    const pending = { patch: new Set<string>(), insert: new Set<string>(), remove: new Set<string>() };

    const clear = (): void => {
        pending.patch.clear();
        pending.insert.clear();
        pending.remove.clear();
    };

    const startOver = (): void => {
        bridge = null;
        page++;
        rendering = false;
        clear();
        reload();
    };

    const queue = (plan: PreviewPlan): void => {
        for (const key of plan.remove) {
            // A block never shown in the page needs no removing
            if (!pending.insert.delete(key)) {
                pending.patch.delete(key);
                pending.remove.add(key);
            }
        }

        for (const key of plan.insert) {
            pending.remove.delete(key);
            pending.patch.delete(key);
            pending.insert.add(key);
        }

        for (const key of plan.patch) {
            if (!pending.insert.has(key)) {
                pending.patch.add(key);
            }
        }
    };

    const flush = (): void => {
        if (bridge === null || rendering) {
            return;
        }

        const { post, token } = bridge;
        pending.remove.forEach((key) => post({ type: 'remove', key }));
        pending.remove.clear();

        const blocks = store.getState().entry.tree.blocks;
        const patch = [...pending.patch].filter((key) => findBlock(blocks, key) !== undefined);
        const insert = [...pending.insert].filter((key) => findBlock(blocks, key) !== undefined);
        pending.patch.clear();
        pending.insert.clear();
        if (patch.length === 0 && insert.length === 0) {
            return;
        }

        rendering = true;
        const forPage = page;
        render(token, [...patch, ...insert].map((key) => findBlock(blocks, key) as StoredBlock)).then(
            (html) => {
                if (forPage !== page || bridge === null) {
                    return;
                }

                rendering = false;

                const rendered = (key: string): RenderedBlock => ({ key, html: html[key] ?? '' });
                if (patch.length > 0) {
                    bridge.post({ type: 'patch', blocks: patch.map(rendered) });
                }

                for (const key of insert) {
                    bridge.post({ type: 'insert', block: rendered(key), ...siblings(key) });
                }

                flush();
            },
            () => {
                if (forPage === page) {
                    startOver();
                }
            },
        );
    };

    /** The blocks before and after a block in its list, nearest first, which the bridge puts it beside. */
    const siblings = (key: string): { previous: string[]; next: string[] } => {
        const { entry } = store.getState();
        const spot = locateBlock(entry.tree.blocks, key);
        const keys = spot === undefined ? [] : blockList(entry, spot.parent).map((block) => block.key);
        const index = spot?.index ?? 0;

        return { previous: keys.slice(0, index).reverse(), next: keys.slice(index + 1) };
    };

    const unsubscribe = store.subscribe((state, previous) => {
        if (state.entry !== previous.entry) {
            // An entry loaded from the server rather than edited, such as the published content after discarding
            // changes, is what the server renders, so the page reloads
            const plan: PreviewPlan = state.revision === previous.revision ? { reload: true, patch: [], insert: [], remove: [] } : planPreviewUpdate(previous.entry, state.entry);
            if (plan.reload) {
                startOver();
            } else {
                queue(plan);
                flush();
            }
        }

        if (state.selectedKey !== previous.selectedKey) {
            bridge?.post({ type: 'select', key: state.selectedKey });
        }
    });

    return {
        connect(post, token) {
            bridge = { post, token };
            page++;
            rendering = false;
            post({ type: 'init', strings });
            post({ type: 'select', key: store.getState().selectedKey });
            flush();
        },

        disconnect() {
            bridge = null;
            page++;
            rendering = false;
        },

        receive(message) {
            const state = store.getState();
            const blocks = state.entry.tree.blocks;
            const known = 'key' in message && findBlock(blocks, message.key) !== undefined;

            switch (message.type) {
                case 'select':
                    if (known) {
                        state.selectBlock(message.key);
                    }
                    break;
                case 'select_parent':
                    if (known) {
                        state.selectBlock(locateBlock(blocks, message.key)?.parent ?? null);
                    }
                    break;
                case 'settings':
                    if (known) {
                        state.selectBlock(message.key);
                        openSettings();
                    }
                    break;
                case 'delete':
                    if (known) {
                        state.removeBlock(message.key);
                    }
                    break;
                case 'add': {
                    const spot = locateBlock(blocks, message.key);
                    if (spot !== undefined) {
                        openBlockPicker({ parent: spot.parent, index: spot.index + (message.position === 'after' ? 1 : 0) }, { x: message.x, y: message.y });
                    }
                    break;
                }
                case 'missing':
                    startOver();
                    break;
                case 'edit': {
                    // Through the same path as an edit in the right pane, so it autosaves and undoes the same way
                    const type = state.blockTypes.find((blockType) => blockType.code === findBlock(blocks, message.key)?.type);
                    const schema = type?.fields.find((field) => field.name === message.field)?.schema;
                    if (schema !== undefined && typedInPlace(schema['x-cms-type'], message.value)) {
                        state.setFieldValue(message.key, message.field, message.value);
                    }
                    break;
                }
                case 'ready':
                    break;
            }
        },

        dispose() {
            unsubscribe();
            bridge = null;
            page++;
        },
    };
}

/** Whether the value fits a field of the field type, when that's a field type editors type in place. */
function typedInPlace(fieldType: string, value: JsonValue | null): boolean {
    switch (fieldType) {
        case 'text':
        case 'textarea':
            return value === null || typeof value === 'string';
        case 'rich_text':
            return value === null || (isJsonObject(value) && value['type'] === 'doc');
        default:
            return false;
    }
}
