import * as Popover from '@radix-ui/react-popover';
import { useId, useState, type ReactNode } from 'react';
import { useBuilder, useBuilderState } from '../context';
import { contextNames, providedContext } from '../hostPageContext';
import { useTranslator } from '../i18n';
import { allowedIn, insertableIn, leftOutIn, type NestingRules } from '../nesting';
import type { BlockSpot } from '../tree';
import type { BlockTypeDefinition, Entry, StoredBlock } from '../types';
import styles from './BlockPicker.module.css';
import { PresetsTab } from './PresetsTab';

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
    const entry = useBuilderState((state) => state.entry);
    const [open, setOpen] = useState(false);
    const insertable = insertableIn(rules, entry.tree.blocks, parent, providedContext(rules.provides, entry));
    const only = soleBlockType(rules, entry, parent);

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
                        onClose={() => setOpen(false)}
                    />
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}

/**
 * The one block type a spot allows, such as Column in Columns, which is added straight away instead of picked, as long as
 * the entry provides what it needs.
 */
export function soleBlockType(rules: NestingRules, entry: Entry, parent: string | null): BlockTypeDefinition | undefined {
    const tree: readonly StoredBlock[] = entry.tree.blocks;

    return allowedIn(rules, tree, parent).length === 1 ? insertableIn(rules, tree, parent, providedContext(rules.provides, entry))[0] : undefined;
}

export interface BlockPickerAtProps {
    /** The spot to add the picked block at, or null while the picker is closed. */
    spot: BlockSpot | null;
    onClose: () => void;
    /** What the picker opens next to. */
    children: ReactNode;
}

/**
 * The block picker for a spot anywhere in the tree, opened from outside the tree, such as by + between blocks in the
 * preview. It adds the picked block at the spot's index.
 */
export function BlockPickerAt({ spot, onClose, children }: BlockPickerAtProps) {
    const t = useTranslator();
    const { store } = useBuilder();

    return (
        <Popover.Root open={spot !== null} onOpenChange={(open) => !open && onClose()}>
            <Popover.Anchor asChild>{children}</Popover.Anchor>
            {spot !== null && (
                <Popover.Portal>
                    <Popover.Content className={styles.picker} aria-label={t('setono_sylius_cms.builder.add_block')} side="bottom" align="center" sideOffset={8} collisionPadding={8}>
                        <BlockPicker
                            parent={spot.parent}
                            index={spot.index}
                            onPick={(code) => {
                                store.getState().addBlock(code, spot.parent, spot.index);
                                onClose();
                            }}
                            onClose={onClose}
                        />
                    </Popover.Content>
                </Popover.Portal>
            )}
        </Popover.Root>
    );
}

interface BlockPickerProps {
    parent: string | null;
    /** Where in the spot's list a preset's blocks go, or at the end. */
    index?: number | undefined;
    onPick: (code: string) => void;
    onClose: () => void;
}

/**
 * The block picker: its Blocks tab, and its Presets tab when the builder page gives the presets' endpoints. See "Block
 * picker" in docs/spec/builder.md
 */
function BlockPicker({ parent, index, onPick, onClose }: BlockPickerProps) {
    const t = useTranslator();
    const { presets } = useBuilder();
    const [tab, setTab] = useState<'blocks' | 'presets'>('blocks');

    return (
        <div className={styles.body}>
            {presets !== null && (
                <div role="tablist" className={styles.tabs}>
                    {(['blocks', 'presets'] as const).map((name) => (
                        <button key={name} type="button" role="tab" aria-selected={tab === name} className={styles.tab} onClick={() => setTab(name)}>
                            {t(name === 'blocks' ? 'setono_sylius_cms.builder.blocks_tab' : 'setono_sylius_cms.builder.presets_tab')}
                        </button>
                    ))}
                </div>
            )}
            {tab === 'blocks' ? <BlockTypes parent={parent} onPick={onPick} /> : <PresetsTab parent={parent} index={index} onInserted={onClose} />}
        </div>
    );
}

/**
 * The block types that fit a spot, each with its thumbnail, and a search. Those the spot would take but for the
 * host-page context they need, which the entry doesn't provide or the snippet doesn't declare, are named below with
 * what they need. See "Block picker" in docs/spec/builder.md
 */
function BlockTypes({ parent, onPick }: { parent: string | null; onPick: (code: string) => void }) {
    const t = useTranslator();
    const searchId = useId();
    const leftOutId = useId();
    const rules = useBuilderState((state) => state.rules);
    const entry = useBuilderState((state) => state.entry);
    const contextKeys = useBuilderState((state) => state.contextKeys);
    const [query, setQuery] = useState('');
    const search = query.trim().toLocaleLowerCase();
    const matches = (blockType: BlockTypeDefinition): boolean => blockType.label.toLocaleLowerCase().includes(search);
    const provided = providedContext(rules.provides, entry);
    const blockTypes = insertableIn(rules, entry.tree.blocks, parent, provided).filter(matches);
    const leftOut = leftOutIn(rules, entry.tree.blocks, parent, provided).filter(({ blockType }) => matches(blockType));

    return (
        <>
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
            {leftOut.length > 0 && (
                <>
                    <h3 id={leftOutId} className={styles.leftOutHeading}>
                        {t('setono_sylius_cms.builder.not_available_here')}
                    </h3>
                    <ul className={styles.leftOut} aria-labelledby={leftOutId}>
                        {leftOut.map(({ blockType, missing }) => (
                            <li key={blockType.code} className={styles.leftOutItem}>
                                <span className={styles.label}>{blockType.label}</span>
                                <span className={styles.reason}>{t('setono_sylius_cms.builder.needs_context', { '%context%': contextNames(missing, contextKeys, t).join(', ') })}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </>
    );
}
