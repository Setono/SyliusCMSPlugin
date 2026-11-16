import { useEffect, useId, useRef } from 'react';
import { useBuilder } from '../context';
import { leavesOn, shortcutOf } from '../shortcuts';
import { BlockTreePane } from './BlockTreePane';
import { ClashDialog } from './ClashDialog';
import { Preview } from './Preview';
import { SelectionPane } from './SelectionPane';
import styles from './Shell.module.css';
import { TopBar } from './TopBar';

/** The builder's full-screen layout: a top bar over the block tree, the preview and the selection. */
export function Shell() {
    const treeHeadingId = useId();
    const selection = useRef<HTMLElement>(null);
    const { store, leave } = useBuilder();

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
        <div className={styles.shell}>
            <header className={styles.topBar}>
                <TopBar />
            </header>
            <nav className={styles.tree} aria-labelledby={treeHeadingId}>
                <BlockTreePane headingId={treeHeadingId} />
            </nav>
            <Preview className={styles.preview} onOpenSettings={openSettings} />
            <aside ref={selection} className={styles.selection}>
                <SelectionPane />
            </aside>
            <ClashDialog />
        </div>
    );
}
