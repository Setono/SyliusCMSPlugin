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
