import * as Popover from '@radix-ui/react-popover';
import { useId, useState, type ReactNode } from 'react';
import { useBuilder, useBuilderState } from '../context';
import { useTranslator } from '../i18n';
import { allowedIn, insertableIn } from '../nesting';
import styles from './BlockPicker.module.css';

export interface AddBlockButtonProps {
    /** The block whose children the new block joins, or null for the top level. */
    parent: string | null;
    className?: string | undefined;
    /** The button's accessible name, when its content isn't text. */
    label?: string | undefined;
    children: ReactNode;
}

/**
 * A + button that opens the block picker for one spot in the tree. A spot that allows only one block type,
 * such as the Columns of a Columns block, gets that block straight away instead.
 */
export function AddBlockButton({ parent, className, label, children }: AddBlockButtonProps) {
    const t = useTranslator();
    const { store } = useBuilder();
    const rules = useBuilderState((state) => state.rules);
    const tree = useBuilderState((state) => state.entry.tree.blocks);
    const [open, setOpen] = useState(false);
    const insertable = insertableIn(rules, tree, parent);
    const allowed = allowedIn(rules, tree, parent);
    const only = allowed.length === 1 ? insertable[0] : undefined;

    const onOpenChange = (next: boolean): void => {
        if (next && only !== undefined) {
            store.getState().addBlock(only.code, parent);

            return;
        }

        setOpen(next);
    };

    return (
        <Popover.Root open={open} onOpenChange={onOpenChange}>
            <Popover.Trigger asChild>
                <button type="button" className={className} aria-label={label} title={label} disabled={insertable.length === 0}>
                    {children}
                </button>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content className={styles.picker} aria-label={t('setono_sylius_cms.builder.add_block')} side="right" align="start" sideOffset={8} collisionPadding={8}>
                    <BlockPicker
                        parent={parent}
                        onPick={(code) => {
                            store.getState().addBlock(code, parent);
                            setOpen(false);
                        }}
                    />
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}

/** The block types that fit a spot, each with its thumbnail, and a search. */
function BlockPicker({ parent, onPick }: { parent: string | null; onPick: (code: string) => void }) {
    const t = useTranslator();
    const searchId = useId();
    const rules = useBuilderState((state) => state.rules);
    const tree = useBuilderState((state) => state.entry.tree.blocks);
    const [query, setQuery] = useState('');
    const search = query.trim().toLocaleLowerCase();
    const blockTypes = insertableIn(rules, tree, parent).filter((blockType) => blockType.label.toLocaleLowerCase().includes(search));

    return (
        <div className={styles.body}>
            <label htmlFor={searchId} className={styles.visuallyHidden}>
                {t('setono_sylius_cms.builder.search_block_types')}
            </label>
            <input
                id={searchId}
                type="search"
                className={styles.search}
                placeholder={t('setono_sylius_cms.builder.search_block_types')}
                value={query}
                onChange={(event) => setQuery(event.currentTarget.value)}
            />
            {blockTypes.length === 0 ? (
                <p className={styles.empty}>{t('setono_sylius_cms.builder.no_matching_block_types')}</p>
            ) : (
                <ul className={styles.list}>
                    {blockTypes.map((blockType) => (
                        <li key={blockType.code}>
                            <button type="button" className={styles.option} onClick={() => onPick(blockType.code)}>
                                {blockType.thumbnail === null ? (
                                    <span className={styles.thumbnail} aria-hidden="true" />
                                ) : (
                                    <img className={styles.thumbnail} src={blockType.thumbnail} alt="" width={120} height={80} />
                                )}
                                <span className={styles.label}>{blockType.label}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
