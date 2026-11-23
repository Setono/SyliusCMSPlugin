import { useId, useState } from 'react';
import { useBuilder, useBuilderState, useValidationState } from '../context';
import { DISPLAY_FIELD, ENTRIES_BLOCK, ENTRIES_FIELD, offeredDisplayChoices } from '../entriesBlock';
import { FieldRow } from '../form/FieldRow';
import formStyles from '../form/Form.module.css';
import { useTranslator } from '../i18n';
import { fieldFallback, outdatedValue } from '../locales';
import { canDuplicate } from '../nesting';
import { isSnippetBlock, shownSnippet } from '../snippets';
import { locks } from '../store';
import { canShift, findBlock, pathTo, topmostBlocks } from '../tree';
import type { BlockStyleDefinition, JsonValue, StoredBlock } from '../types';
import { blockMessages, fieldMessages } from '../validation';
import { ErrorList } from './ErrorList';
import { translatedValues } from '../bindings';
import { useOfferedDisplays } from '../sources/context';
import { readFieldValue } from '../values';
import { AddBlockButton } from './BlockPicker';
import { blockHint, blockLabel } from './BlockTreePane';
import { ArrowDownIcon, ArrowUpIcon, CopyIcon, PlusIcon, StarIcon, TrashIcon } from './icons';
import styles from './SelectionPane.module.css';
import { SettingsPane } from './SettingsPane';
import { VisibilityConditions } from './VisibilityConditions';

export interface SelectionPaneProps {
    /** ★: opens Save as preset for the blocks with these keys. Without it, there's no ★. */
    onSavePreset?: ((keys: readonly string[]) => void) | undefined;
    /** Opens Save as starting point for the whole tree, from the entry's settings. Without it, there's none. */
    onSaveStartingPoint?: (() => void) | undefined;
}

/**
 * The right pane: the selected block's path, its actions, its block style when its block type offers any, its fields,
 * rendered from their schemas, its children and its visibility conditions, or the entry's settings. With several blocks selected, the actions for all of them.
 */
export function SelectionPane({ onSavePreset, onSaveStartingPoint }: SelectionPaneProps = {}) {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const block = useBuilderState((state) => (state.selectedKey === null ? undefined : findBlock(state.entry.tree.blocks, state.selectedKey)));
    const blockType = useBuilderState((state) => (block === undefined ? undefined : state.rules.blockTypes.get(block.type)));
    const locale = useBuilderState((state) => state.locale);
    const selection = useBuilderState((state) => state.selection);
    const errors = useValidationState((state) => state.errors);
    // While a scheduled publish locks the working copy, the block is only looked at
    const locked = useBuilderState((state) => locks(state.schedule));
    // An Entries block offers the displays the content type it lists has cards for
    const listsEntries = block?.type === ENTRIES_BLOCK;
    const offeredDisplays = useOfferedDisplays(listsEntries ? block?.fields[ENTRIES_FIELD] : undefined, listsEntries);

    if (selection.length > 1) {
        return (
            <div className={styles.pane}>
                <h2 className={styles.heading}>{t('setono_sylius_cms.builder.blocks_selected', { '%count%': String(selection.length) })}</h2>
                <fieldset className={styles.locked} disabled={locked}>
                    <Actions keys={selection} onSavePreset={onSavePreset} />
                </fieldset>
            </div>
        );
    }

    // With nothing selected, or the entry's settings selected, it shows the settings
    if (block === undefined) {
        return <SettingsPane onSaveStartingPoint={onSaveStartingPoint} />;
    }

    const label = blockLabel(block, blockType, t);

    return (
        <div className={styles.pane}>
            <Path block={block} />
            <h2 className={styles.heading}>{label}</h2>
            {blockType === undefined && <p className={styles.unknown}>{t('setono_sylius_cms.builder.unknown_block_type_help')}</p>}
            <BlockErrors blockKey={block.key} />
            <fieldset className={styles.locked} disabled={locked}>
            <Actions keys={[block.key]} onSavePreset={onSavePreset} />
            {isSnippetBlock(block) && <SharedSnippet block={block} />}
            {blockType !== undefined && blockType.styles.length > 0 && <StylePicker block={block} styles={blockType.styles} />}
            <div key={block.key} className={styles.fields}>
                {blockType?.fields.map(({ name, schema }) => (
                    <FieldRow
                        key={name}
                        id={`cms-field-${block.key}-${name}`}
                        schema={listsEntries && name === DISPLAY_FIELD ? offeredDisplayChoices(schema, offeredDisplays, displayOf(block.fields[name])) : schema}
                        locale={locale}
                        value={readFieldValue(block.fields, name, schema['x-cms-translatable'], locale)}
                        onChange={(value) => store.getState().setFieldValue(block.key, name, value)}
                        errors={fieldMessages(errors, block.key, name, locale)}
                        // A bound field's fallback is translated like its value
                        fallback={schema['x-cms-translatable'] ? fieldFallback(locales, translatedValues(block.fields[name]), locale) : undefined}
                        binding={{ stored: block.fields[name], set: (value) => store.getState().setFieldBinding(block.key, name, value) }}
                        outdated={
                            schema['x-cms-translatable']
                                ? outdatedValue(locales, translatedValues(block.fields[name]), block.translated[name], locale, () => store.getState().markValueUpToDate(block.key, name))
                                : undefined
                        }
                    />
                ))}
            </div>
            {(blockType?.children.length ?? 0) > 0 && <Children block={block} label={label} />}
            <VisibilityConditions key={`conditions-${block.key}`} block={block} />
            </fieldset>
        </div>
    );
}

/**
 * What a Snippet block shows is shared: it says so, and offers Edit snippet, which opens the snippet in the builder
 * with a way back here, and Detach to edit here, which puts a copy of the snippet's blocks in the block's place. See
 * "Snippet references" in docs/spec/builder.md
 */
function SharedSnippet({ block }: { block: StoredBlock }) {
    const t = useTranslator();
    const { store, editSnippet, snippetBlocks } = useBuilder();
    const [detaching, setDetaching] = useState(false);
    const [failed, setFailed] = useState(false);
    const snippet = shownSnippet(block);

    const detach = (shown: string, load: (snippet: string) => Promise<StoredBlock[]>): void => {
        setDetaching(true);
        setFailed(false);
        load(shown).then(
            (blocks) => {
                setDetaching(false);
                setFailed(store.getState().detachSnippet(block.key, blocks) === null);
            },
            () => {
                setDetaching(false);
                setFailed(true);
            },
        );
    };

    return (
        <section className={styles.shared} aria-label={t('setono_sylius_cms.builder.snippet.shared')}>
            <p className={styles.sharedHelp}>
                <span className={styles.sharedBadge}>{t('setono_sylius_cms.builder.snippet.shared')}</span> {t('setono_sylius_cms.builder.snippet.shared_help')}
            </p>
            {snippet !== null && (editSnippet !== null || snippetBlocks !== null) && (
                <div className={styles.sharedActions}>
                    {editSnippet !== null && (
                        <button type="button" className={formStyles.button} onClick={() => editSnippet(snippet, block.key)}>
                            {t('setono_sylius_cms.builder.snippet.edit')}
                        </button>
                    )}
                    {snippetBlocks !== null && (
                        <button type="button" className={formStyles.button} disabled={detaching} onClick={() => detach(snippet, snippetBlocks)}>
                            {t('setono_sylius_cms.builder.snippet.detach')}
                        </button>
                    )}
                </div>
            )}
            {failed && (
                <p role="alert" className={styles.sharedFailed}>
                    {t('setono_sylius_cms.builder.snippet.detach_failed')}
                </p>
            )}
        </section>
    );
}

/** The errors about the block as a whole, such as a block type its spot can't hold, which publish would refuse. */
function BlockErrors({ blockKey }: { blockKey: string }) {
    const errors = useValidationState((state) => state.errors);

    return <ErrorList messages={blockMessages(errors, blockKey)} />;
}

/** Where the block is in the tree: the blocks it's inside, each selectable, and then the block itself. */
function Path({ block }: { block: StoredBlock }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const blocks = useBuilderState((state) => state.entry.tree.blocks);
    const blockTypes = useBuilderState((state) => state.rules.blockTypes);
    const path = pathTo(blocks, block.key);
    const labelOf = (step: StoredBlock): string => blockLabel(step, blockTypes.get(step.type), t);

    // A list rather than a nav landmark, so the tree stays the builder's one navigation
    return (
        <ol aria-label={t('setono_sylius_cms.builder.path')} className={styles.path}>
            {path.slice(0, -1).map((step) => (
                <li key={step.key} className={styles.step}>
                    <button type="button" className={styles.stepButton} onClick={() => store.getState().selectBlock(step.key)}>
                        {labelOf(step)}
                    </button>
                </li>
            ))}
            <li className={styles.step}>
                <span aria-current="location" className={styles.current}>
                    {labelOf(block)}
                </span>
            </li>
        </ol>
    );
}

/**
 * Move, duplicate, save as preset (★) and delete, for the selected blocks. A block inside another selected block counts
 * once.
 */
function Actions({ keys, onSavePreset }: { keys: readonly string[]; onSavePreset: SelectionPaneProps['onSavePreset'] }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const canMoveUp = useBuilderState((state) => canShift(state.entry.tree.blocks, keys, 'up'));
    const canMoveDown = useBuilderState((state) => canShift(state.entry.tree.blocks, keys, 'down'));
    const duplicable = useBuilderState((state) =>
        canDuplicate(
            state.rules,
            state.entry.tree.blocks,
            topmostBlocks(state.entry.tree.blocks, keys).map((selected) => selected.key),
        ),
    );
    const actions = [
        { label: t('setono_sylius_cms.builder.move_up'), icon: <ArrowUpIcon />, enabled: canMoveUp, act: () => store.getState().shiftBlocks(keys, 'up') },
        { label: t('setono_sylius_cms.builder.move_down'), icon: <ArrowDownIcon />, enabled: canMoveDown, act: () => store.getState().shiftBlocks(keys, 'down') },
        { label: t('setono_sylius_cms.builder.duplicate'), icon: <CopyIcon />, enabled: duplicable, act: () => store.getState().duplicateBlocks(keys) },
        ...(onSavePreset === undefined ? [] : [{ label: t('setono_sylius_cms.builder.save_as_preset'), icon: <StarIcon />, enabled: true, act: () => onSavePreset(keys) }]),
        { label: t('setono_sylius_cms.builder.delete_block'), icon: <TrashIcon />, enabled: true, act: () => store.getState().removeBlocks(keys) },
    ];

    return (
        <div className={styles.actions}>
            {actions.map(({ label, icon, enabled, act }) => (
                <button key={label} type="button" className={styles.action} aria-label={label} title={label} disabled={!enabled} onClick={act}>
                    {icon}
                </button>
            ))}
        </div>
    );
}

/**
 * The block styles the block type offers, and none. A style the block has that's no longer declared shows as none,
 * as it renders.
 */
function StylePicker({ block, styles }: { block: StoredBlock; styles: readonly BlockStyleDefinition[] }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const id = `cms-style-${block.key}`;
    const picked = styles.some((style) => style.code === block.style) ? (block.style ?? '') : '';

    return (
        <div className={formStyles.field}>
            <div className={formStyles.labelRow}>
                <label htmlFor={id} className={formStyles.label}>
                    {t('setono_sylius_cms.builder.block_style')}
                </label>
            </div>
            <select
                id={id}
                className={formStyles.select}
                value={picked}
                onChange={(event) => store.getState().setBlockStyle(block.key, event.currentTarget.value === '' ? null : event.currentTarget.value)}
            >
                <option value="">{t('setono_sylius_cms.builder.no_style')}</option>
                {styles.map((style) => (
                    <option key={style.code} value={style.code}>
                        {style.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Children({ block, label }: { block: StoredBlock; label: string }) {
    const t = useTranslator();
    const headingId = useId();
    const { store } = useBuilder();
    const blockTypes = useBuilderState((state) => state.rules.blockTypes);
    const locale = useBuilderState((state) => state.locale);

    return (
        <section className={styles.children} aria-labelledby={headingId}>
            <div className={styles.childrenHeader}>
                <h3 id={headingId} className={styles.subheading}>
                    {t('setono_sylius_cms.builder.children')}
                </h3>
                <AddBlockButton parent={block.key} className={styles.add} label={t('setono_sylius_cms.builder.add_block_inside', { '%block%': label })}>
                    <PlusIcon />
                </AddBlockButton>
            </div>
            {block.children.length > 0 && (
                <ul className={styles.childList}>
                    {block.children.map((child) => {
                        const childType = blockTypes.get(child.type);
                        const hint = blockHint(child, childType, locale);

                        return (
                            <li key={child.key}>
                                <button type="button" className={styles.child} onClick={() => store.getState().selectBlock(child.key)}>
                                    <span className={styles.childLabel}>{blockLabel(child, childType, t)}</span>
                                    {hint !== '' && <span className={styles.childHint}>{hint}</span>}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

/** The display an Entries block holds, or undefined while it holds the default. */
function displayOf(value: JsonValue | undefined): string | undefined {
    return typeof value === 'string' ? value : undefined;
}
