import { useId } from 'react';
import { BlockTreePane } from './BlockTreePane';
import { Preview } from './Preview';
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
            <Preview className={styles.preview} />
            <aside className={styles.selection}>
                <SelectionPane />
            </aside>
        </div>
    );
}
