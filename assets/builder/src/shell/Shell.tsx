import { useEffect, useId, useRef, useState } from 'react';
import { useBuilder } from '../context';
import { leavesOn, shortcutOf } from '../shortcuts';
import type { PresetContent } from '../types';
import { BlockTreePane } from './BlockTreePane';
import { ClashDialog } from './ClashDialog';
import { LeaveDialog } from './LeaveDialog';
import { Preview } from './Preview';
import { SavePresetDialog } from './SavePresetDialog';
import { SelectionPane } from './SelectionPane';
import styles from './Shell.module.css';
import { TopBar } from './TopBar';

/**
 * The builder's full-screen layout: a top bar over the block tree, the preview and the selection. An entry whose content
 * type has no block tree is still edited here, with the tree pane hidden. See "Opening and leaving" in docs/spec/builder.md
 */
export function Shell() {
    const treeHeadingId = useId();
    const selection = useRef<HTMLElement>(null);
    const { store, leave, hasBlockTree, presets } = useBuilder();
    // What's saved as a preset while the dialog is open: the blocks ★ saves, from the right pane or the preview's
    // toolbar, or the whole tree the entry settings save as a starting point, which needs a block tree
    const [presetContent, setPresetContent] = useState<PresetContent | null>(null);
    const savePreset = presets !== null && presets.editing !== null ? setPresetContent : undefined;
    const saveStartingPoint = savePreset !== undefined && hasBlockTree ? () => savePreset('tree') : undefined;

    // Undo and redo work on the working copy wherever the focus is, also in a field: its value is the working copy's.
    // A control with an undo of its own, such as rich text, handles the keys first. Esc leaves the builder, unless a
    // field or a dialog has it.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent): void => {
            const shortcut = shortcutOf(event);
            if (shortcut !== null && !event.defaultPrevented) {
                event.preventDefault();
                store.getState()[shortcut]();
            } else if (leavesOn(event)) {
                event.preventDefault();
                leave();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [store, leave]);

    // Once the right pane shows the selected block, its first field takes the focus
    const openSettings = (): void => {
        setTimeout(() => selection.current?.querySelector<HTMLElement>('input, select, textarea')?.focus());
    };

    return (
        <div className={hasBlockTree ? styles.shell : `${styles.shell} ${styles.withoutTree}`}>
            <header className={styles.topBar}>
                <TopBar />
            </header>
            {hasBlockTree && (
                <nav className={styles.tree} aria-labelledby={treeHeadingId}>
                    <BlockTreePane headingId={treeHeadingId} />
                </nav>
            )}
            <Preview className={styles.preview} onOpenSettings={openSettings} onSavePreset={savePreset} />
            <aside ref={selection} className={styles.selection}>
                <SelectionPane onSavePreset={savePreset} onSaveStartingPoint={saveStartingPoint} />
            </aside>
            <ClashDialog />
            <LeaveDialog />
            {savePreset !== undefined && <SavePresetDialog content={presetContent} onClose={() => setPresetContent(null)} />}
        </div>
    );
}
