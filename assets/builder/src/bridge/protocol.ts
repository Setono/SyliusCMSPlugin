/**
 * The messages the builder and the bridge in its preview exchange by postMessage. The protocol is internal: both ends
 * ship together in the plugin, so it can change in any release (docs/spec/builder.md#the-bridge).
 */
import type { JsonValue } from '../types';

/** The bridge's UI strings, which the builder sends it, translated, from the registry. */
export interface BridgeStrings {
    selectParent: string;
    settings: string;
    delete: string;
    addBefore: string;
    addAfter: string;
    moveUp: string;
    moveDown: string;
    duplicate: string;
    /** The toolbar of rich text typed in place, and its marks, as the right pane's toolbar names them. */
    formatting: string;
    bold: string;
    italic: string;
    underline: string;
    strike: string;
    code: string;
}

/** A block's HTML as preview_render answers it: its output, bracketed by its markers. */
export interface RenderedBlock {
    key: string;
    html: string;
}

/** From the builder to the bridge. */
export type ToBridge =
    | { type: 'init'; strings: BridgeStrings }
    /** Outlines the block, or nothing when the key is null, and scrolls to it. */
    | { type: 'select'; key: string | null }
    /** Morphs blocks that are in the page into their new HTML. */
    | { type: 'patch'; blocks: RenderedBlock[] }
    /**
     * Puts a block at its spot in the page: after the first of the blocks before its spot that is in the page (nearest
     * first), or else before the first of the blocks after it. A block that's elsewhere in the page moves there.
     */
    | { type: 'insert'; block: RenderedBlock; previous: string[]; next: string[] }
    | { type: 'remove'; key: string };

/** From the bridge to the builder. */
export type FromBridge =
    | { type: 'ready' }
    | { type: 'select'; key: string }
    | { type: 'select_parent'; key: string }
    | { type: 'settings'; key: string }
    | { type: 'delete'; key: string }
    /** One place up or down in its list. */
    | { type: 'move'; key: string; direction: 'up' | 'down' }
    | { type: 'duplicate'; key: string }
    /** ⌘Z or ⇧⌘Z pressed in the page. */
    | { type: 'undo' }
    | { type: 'redo' }
    /** Esc pressed in the page, outside a field: the editor leaves the builder. */
    | { type: 'leave' }
    /** + next to a block: the editor wants to add a block at that spot. x and y are where the + is in the page's viewport. */
    | { type: 'add'; key: string; position: 'before' | 'after'; x: number; y: number }
    /** A block the builder patched or inserted has no place in the page, so only a reload shows it. */
    | { type: 'missing'; key: string }
    /**
     * The editor typed in place: the field's new value in the edited locale, a string for text and textareas and a
     * document for rich text. null means it's empty.
     */
    | { type: 'edit'; key: string; field: string; value: JsonValue | null };

/** Tells the plugin's messages apart from any other postMessage traffic the shop page has. */
const CHANNEL = 'setono_sylius_cms.bridge';

export function envelope(message: ToBridge | FromBridge): { channel: string; message: ToBridge | FromBridge } {
    return { channel: CHANNEL, message };
}

/** The message in an envelope, or null for anything else. Both ends are the plugin's own code, so only the envelope is checked. */
export function openEnvelope<T extends ToBridge | FromBridge>(data: unknown): T | null {
    if (typeof data !== 'object' || data === null) {
        return null;
    }

    const { channel, message } = data as { channel?: unknown; message?: unknown };
    if (channel !== CHANNEL || typeof message !== 'object' || message === null || typeof (message as { type?: unknown }).type !== 'string') {
        return null;
    }

    return message as T;
}
