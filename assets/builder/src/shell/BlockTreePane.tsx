import { createContext, useContext, useRef, useState, type DragEvent } from 'react';
import { hiddenInPreview } from '../conditions';
import { useBuilder, useBuilderState, useFallbackChain, usePreviewState, useValidationState } from '../context';
import { useTranslator, type Translate } from '../i18n';
import { linkShape, offeredTargets, readLink, writeLink } from '../links';
import { useLinkRef, useLinkTargets } from '../linkTargets';
import { canMove, canMoveBlocks, placement, type DropZone } from '../nesting';
import { actedOn, locks } from '../store';
import { findBlock } from '../tree';
import type { BlockTypeDefinition, FieldDefinition, StoredBlock } from '../types';
import { blockHasErrors } from '../validation';
import { SNIPPET } from '../form/SnippetControl';
import { useKnownRef } from '../search/context';
import { isSnippetBlock, shownSnippet } from '../snippets';
import { readFieldValue } from '../values';
import { AddBlockButton } from './BlockPicker';
import styles from './BlockTreePane.module.css';
import { AlertIcon, EyeSlashIcon, GripIcon, LinkOffIcon, PlusIcon, TrashIcon } from './icons';

/** Where the dragged block would land, and whether the nesting rules let it. */
interface DropTarget {
    key: string;
    zone: DropZone;
    allowed: boolean;
}

interface DragState {
    dragged: string | null;
    target: DropTarget | null;
}

interface Dragging extends DragState {
    /** The drag as it is now, also between a drag event and the render it causes. */
    now(): DragState;
    start(key: string): void;
    over(target: DropTarget | null): void;
    drop(): void;
    end(): void;
}

const DraggingContext = createContext<Dragging | null>(null);

/** The left pane: the block tree. Rows can be dragged to reorder and nest blocks, within the nesting rules. */
export function BlockTreePane({ headingId }: { headingId: string }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const blocks = useBuilderState((state) => state.entry.tree.blocks);
    const settingsSelected = useBuilderState((state) => state.selectedKey === null);
    const entryInvalid = useValidationState((state) => state.errors.some((error) => error.block === null));
    const locked = useBuilderState((state) => locks(state.schedule));
    // The drag as it is now, which drag events read; the state copies only re-render the rows.
    const current = useRef<DragState>({ dragged: null, target: null });
    const [dragged, setDragged] = useState<string | null>(null);
    const [target, setTarget] = useState<DropTarget | null>(null);
    const [refused, setRefused] = useState<string | null>(null);

    const update = (next: DragState): void => {
        current.current = next;
        setDragged(next.dragged);
        setTarget(next.target);
    };

    const dragging: Dragging = {
        dragged,
        target,
        now: () => current.current,
        start(key) {
            update({ dragged: key, target: null });
            setRefused(null);
        },
        over(next) {
            update({ ...current.current, target: next });
        },
        drop() {
            const { dragged: key, target: spot } = current.current;
            const state = store.getState();
            const moving = key === null ? [] : actedOn(state.selection, key);
            const place = key === null || spot === null ? null : placement(state.entry.tree.blocks, moving, spot.key, spot.zone);
            if (place !== null) {
                state.moveBlocks(moving, place.parent, place.index);
            }

            update({ dragged: null, target: null });
        },
        end() {
            const { dragged: key, target: spot } = current.current;
            // A drop the rules refuse never fires, so the drag just ends over the refused spot.
            if (key !== null && spot !== null && !spot.allowed) {
                const { entry, rules, selection } = store.getState();
                const tree = entry.tree.blocks;
                const moving = actedOn(selection, key);
                const parent = placement(tree, moving, spot.key, spot.zone)?.parent;
                // The block that can't go there, when several are dragged
                const refusedKey = moving.find((moved) => parent !== undefined && !canMove(rules, tree, moved, parent)) ?? key;
                const type = findBlock(tree, refusedKey)?.type ?? '';
                setRefused(t('setono_sylius_cms.builder.drop_refused', { '%block%': rules.blockTypes.get(type)?.label ?? type }));
            }

            update({ dragged: null, target: null });
        },
    };

    return (
        <div className={styles.pane}>
            {/* The entry's settings sit at the top of the tree. Selecting them deselects the block */}
            <div className={styles.row} data-selected={settingsSelected || undefined} data-invalid={entryInvalid || undefined}>
                <button type="button" className={styles.select} aria-pressed={settingsSelected} onClick={() => store.getState().selectBlock(null)}>
                    <span className={styles.label}>{t('setono_sylius_cms.builder.entry_settings')}</span>
                </button>
                {entryInvalid && <InvalidFlag />}
            </div>
            <h2 id={headingId} className={styles.heading}>
                {t('setono_sylius_cms.builder.blocks')}
            </h2>
            {refused !== null && (
                <p role="alert" className={styles.refused}>
                    {refused}
                </p>
            )}
            {blocks.length === 0 ? (
                <p className={styles.empty}>{t('setono_sylius_cms.builder.no_blocks')}</p>
            ) : (
                <DraggingContext.Provider value={dragging}>
                    <BlockList blocks={blocks} />
                </DraggingContext.Provider>
            )}
            {!locked && (
                <AddBlockButton parent={null} className={styles.add}>
                    <PlusIcon />
                    {t('setono_sylius_cms.builder.add_block')}
                </AddBlockButton>
            )}
        </div>
    );
}

/**
 * A list of blocks in the tree. A block that a channel or locale condition hides in the preview's channel and locale is
 * struck through, and so is everything inside it, which the preview drops with it.
 */
function BlockList({ blocks, hidden = false }: { blocks: readonly StoredBlock[]; hidden?: boolean }) {
    const channel = usePreviewState((state) => state.channel);
    const locale = useBuilderState((state) => state.locale);

    return (
        <ul className={styles.list}>
            {blocks.map((block) => {
                const hiddenHere = hidden || hiddenInPreview(block, channel, locale);

                return (
                    <li key={block.key}>
                        <BlockRow block={block} hidden={hiddenHere} />
                        {block.children.length > 0 && <BlockList blocks={block.children} hidden={hiddenHere} />}
                    </li>
                );
            })}
        </ul>
    );
}

function BlockRow({ block, hidden }: { block: StoredBlock; hidden: boolean }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const dragging = useContext(DraggingContext);
    const selected = useBuilderState((state) => state.selection.includes(block.key));
    const moving = useBuilderState((state) => dragging?.dragged !== null && dragging?.dragged !== undefined && actedOn(state.selection, dragging.dragged).includes(block.key));
    const blockType = useBuilderState((state) => state.rules.blockTypes.get(block.type));
    const locale = useBuilderState((state) => state.locale);
    const invalid = useValidationState((state) => blockHasErrors(state.errors, block.key));
    const unresolvedLink = usePreviewState((state) => state.unresolvedLinks.includes(block.key));
    const label = blockLabel(block, blockType, t);
    const labelField = linkLabelField(blockType);
    const shared = isSnippetBlock(block);
    const snippetName = useKnownRef(SNIPPET, shownSnippet(block))?.name ?? '';
    const hint = shared ? snippetName : labelField === undefined ? blockHint(block, blockType, locale) : '';
    const holdsBlocks = (blockType?.children.length ?? 0) > 0;
    const target = dragging?.target?.key === block.key ? dragging.target : null;
    // While a scheduled publish locks the working copy, blocks are only selected
    const locked = useBuilderState((state) => locks(state.schedule));
    // A row isn't dragged while its label box is edited, so the mouse selects text in it
    const [editingLabel, setEditingLabel] = useState(false);

    const onDragOver = (event: DragEvent<HTMLDivElement>): void => {
        const { dragged, target: previous } = dragging?.now() ?? { dragged: null, target: null };
        const { entry, rules, selection } = store.getState();
        const moved = dragged === null ? [] : actedOn(selection, dragged);
        if (dragging === null || dragged === null || moved.includes(block.key)) {
            return;
        }

        const zone = zoneOf(event, holdsBlocks);
        const spot = placement(entry.tree.blocks, moved, block.key, zone);
        const allowed = spot !== null && canMoveBlocks(rules, entry.tree.blocks, moved, spot.parent);

        if (allowed) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        }

        if (previous?.key !== block.key || previous.zone !== zone || previous.allowed !== allowed) {
            dragging.over({ key: block.key, zone, allowed });
        }
    };

    return (
        <div
            className={blockType === undefined ? `${styles.row} ${styles.unknown}` : styles.row}
            data-selected={selected || undefined}
            data-invalid={invalid || undefined}
            data-hidden={hidden || undefined}
            data-dragged={moving || undefined}
            data-drop={target?.zone}
            data-refused={(target !== null && !target.allowed) || undefined}
            draggable={!locked && !editingLabel}
            onDragStart={(event) => {
                event.stopPropagation();
                event.dataTransfer.effectAllowed = 'move';
                // Some browsers only start a drag that carries data
                event.dataTransfer.setData('text/plain', block.key);
                dragging?.start(block.key);
            }}
            onDragOver={onDragOver}
            onDragLeave={(event) => {
                // A drag that ends over a refused spot leaves it towards nothing, just before it ends: keep the spot, so the end can say why.
                const left = event.relatedTarget instanceof Node && !event.currentTarget.contains(event.relatedTarget);
                if (left && dragging?.now().target?.key === block.key) {
                    dragging.over(null);
                }
            }}
            onDrop={(event) => {
                event.preventDefault();
                dragging?.drop();
            }}
            onDragEnd={() => dragging?.end()}
        >
            <span className={styles.grip} aria-hidden="true">
                <GripIcon />
            </span>
            <button
                type="button"
                className={styles.select}
                aria-pressed={selected}
                onClick={(event) => {
                    // ⌘-click, or Ctrl-click elsewhere, selects several blocks
                    if (event.metaKey || event.ctrlKey) {
                        store.getState().toggleSelection(block.key);
                    } else {
                        store.getState().selectBlock(block.key);
                    }
                }}
            >
                <span className={styles.label}>{label}</span>
                {hint !== '' && <span className={styles.hint}>{hint}</span>}
            </button>
            {labelField !== undefined && <LinkLabelBox block={block} field={labelField} readOnly={locked} onEditing={setEditingLabel} />}
            {shared && <span className={styles.shared}>{t('setono_sylius_cms.builder.snippet.shared')}</span>}
            {hidden && <HiddenFlag />}
            {unresolvedLink && <UnresolvedLinkFlag />}
            {invalid && <InvalidFlag />}
            {holdsBlocks && !locked && (
                <AddBlockButton parent={block.key} className={styles.action} label={t('setono_sylius_cms.builder.add_block_inside', { '%block%': label })}>
                    <PlusIcon />
                </AddBlockButton>
            )}
            {!locked && (
                <button
                    type="button"
                    className={`${styles.action} ${styles.delete}`}
                    aria-label={t('setono_sylius_cms.builder.delete_block')}
                    title={t('setono_sylius_cms.builder.delete_block')}
                    onClick={() => {
                        const state = store.getState();
                        state.removeBlocks(actedOn(state.selection, block.key));
                    }}
                >
                    <TrashIcon />
                </button>
            )}
        </div>
    );
}


/**
 * The top and bottom quarter of a row drop before and after its block. The middle drops inside it, when its
 * block type holds blocks; otherwise the row is split in two.
 */
function zoneOf(event: DragEvent<HTMLElement>, holdsBlocks: boolean): DropZone {
    const { top, height } = event.currentTarget.getBoundingClientRect();
    const y = event.clientY - top;
    const edge = holdsBlocks ? height / 4 : height / 2;

    if (y < edge) {
        return 'before';
    }

    if (y > height - edge) {
        return 'after';
    }

    return holdsBlocks ? 'inside' : 'after';
}

/**
 * The Link field whose label a block's row edits: the one field of a block type that holds nothing but a Link, such as
 * the Link blocks a Link list holds, and the Button. See "Link list and Link" in docs/spec/blocks.md
 */
function linkLabelField(blockType: BlockTypeDefinition | undefined): FieldDefinition | undefined {
    const [only, ...others] = blockType?.fields ?? [];

    return only !== undefined && others.length === 0 && only.schema['x-cms-type'] === 'link' ? only : undefined;
}

interface LinkLabelBoxProps {
    block: StoredBlock;
    field: FieldDefinition;
    readOnly: boolean;
    onEditing: (editing: boolean) => void;
}

/**
 * A link's label in the edited locale, typed right in its block's row. When it's empty, it shows the label it falls
 * back to, or the name of what the link leads to, which the shop shows then.
 */
function LinkLabelBox({ block, field, readOnly, onEditing }: LinkLabelBoxProps) {
    const t = useTranslator();
    const { store } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const chain = useFallbackChain(locale);
    const linkTargets = useLinkTargets();
    const { schema } = field;
    const shape = linkShape(schema);
    const first = offeredTargets(schema, linkTargets)[0]?.code ?? '';
    const value = readFieldValue(block.fields, field.name, schema['x-cms-translatable'], locale);
    const link = readLink(value, shape, chain, first);
    const { refIsTyped, found } = useLinkRef(link);

    return (
        <input
            type="text"
            className={styles.labelBox}
            aria-label={t('setono_sylius_cms.builder.link.row_label')}
            value={link.label}
            placeholder={link.labelFallback ?? found?.name ?? (refIsTyped && link.ref !== '' ? link.ref : undefined)}
            readOnly={readOnly}
            onFocus={() => onEditing(true)}
            onBlur={() => onEditing(false)}
            onChange={(event) => store.getState().setFieldValue(block.key, field.name, writeLink(value, shape, locale, { label: event.currentTarget.value }, linkTargets, first))}
        />
    );
}

/** Flags a row whose block doesn't show in the preview's channel and locale. */
function HiddenFlag() {
    const t = useTranslator();
    const label = t('setono_sylius_cms.builder.hidden_in_preview');

    return (
        <span role="img" className={styles.hidden} aria-label={label} title={label}>
            <EyeSlashIcon />
        </span>
    );
}

/**
 * Flags a row whose block has a link that doesn't resolve in the preview's channel and locale, such as to an entry that
 * isn't published or translated there, so shoppers don't see it. The preview says why.
 */
function UnresolvedLinkFlag() {
    const t = useTranslator();
    const label = t('setono_sylius_cms.builder.unresolved_link');

    return (
        <span role="img" className={styles.unresolvedLink} aria-label={label} title={label}>
            <LinkOffIcon />
        </span>
    );
}

/** Flags a row whose block, or the entry, has errors that publish would refuse. Selecting it shows them. */
function InvalidFlag() {
    const t = useTranslator();
    const label = t('setono_sylius_cms.builder.has_errors');

    return (
        <span role="img" className={styles.invalid} aria-label={label} title={label}>
            <AlertIcon />
        </span>
    );
}

/** The block type's label, or says that the code no longer has the block's type. */
export function blockLabel(block: StoredBlock, blockType: BlockTypeDefinition | undefined, t: Translate): string {
    return blockType?.label ?? t('setono_sylius_cms.builder.unknown_block_type', { '%type%': block.type });
}

/** The value of the block's first text field in the edited locale, to tell blocks of one type apart. */
export function blockHint(block: StoredBlock, blockType: BlockTypeDefinition | undefined, locale: string): string {
    const field = blockType?.fields.find((definition) => definition.schema['x-cms-type'] === 'text');
    if (field === undefined) {
        return '';
    }

    const value = readFieldValue(block.fields, field.name, field.schema['x-cms-translatable'], locale);

    return typeof value === 'string' ? value : '';
}
