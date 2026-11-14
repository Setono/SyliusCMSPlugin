import { useId } from 'react';
import { BlockTreePane } from './BlockTreePane';
import { SelectionPane } from './SelectionPane';
import styles from './Shell.module.css';
import { TopBar } from './TopBar';

/** The builder's full-screen layout: a top bar over the block tree, the preview and the selection. */
export function Shell() {
    const treeHeadingId = useId();

    return (
        <div className={styles.shell}>
            <header className={styles.topBar}>
                <TopBar />
            </header>
            <nav className={styles.tree} aria-labelledby={treeHeadingId}>
                <BlockTreePane headingId={treeHeadingId} />
            </nav>
            {/* The preview of the shop page comes in a later ticket. */}
            <main className={styles.preview} />
            <aside className={styles.selection}>
                <SelectionPane />
            </aside>
        </div>
    );
}
