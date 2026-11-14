import { useId, useRef } from 'react';
import { BlockTreePane } from './BlockTreePane';
import { Preview } from './Preview';
import { SelectionPane } from './SelectionPane';
import styles from './Shell.module.css';
import { TopBar } from './TopBar';

/** The builder's full-screen layout: a top bar over the block tree, the preview and the selection. */
export function Shell() {
    const treeHeadingId = useId();
    const selection = useRef<HTMLElement>(null);

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
        </div>
    );
}
