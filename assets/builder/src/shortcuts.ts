/** The builder's keyboard shortcuts, which the bridge passes on from the preview too. */
export type Shortcut = 'undo' | 'redo';

type KeyPress = Pick<KeyboardEvent, 'key' | 'metaKey' | 'ctrlKey' | 'shiftKey' | 'altKey'>;

/** ⌘Z (Ctrl+Z elsewhere) undoes, and ⇧⌘Z (Ctrl+Shift+Z, or Ctrl+Y) redoes. */
export function shortcutOf(event: KeyPress): Shortcut | null {
    if (!(event.metaKey || event.ctrlKey) || event.altKey) {
        return null;
    }

    const key = event.key.toLowerCase();
    if (key === 'z') {
        return event.shiftKey ? 'redo' : 'undo';
    }

    return key === 'y' && event.ctrlKey && !event.shiftKey ? 'redo' : null;
}

/**
 * Esc leaves the builder, back to where it was opened from, as the back button does (docs/spec/builder.md#opening-and-leaving).
 * Esc pressed in a field is the field's, and Esc that something else handled first, such as a dialog it closed, is
 * that one's.
 */
export function leavesOn(event: KeyPress & Pick<KeyboardEvent, 'target' | 'defaultPrevented'>): boolean {
    return event.key === 'Escape' && !event.defaultPrevented && !(event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) && !inField(event.target);
}

/** Whether the target is a field, or inside one: an input, a text area, a select or something editable. */
function inField(target: EventTarget | null): boolean {
    return target instanceof Element && target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])') !== null;
}
