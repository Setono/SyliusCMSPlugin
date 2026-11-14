import { useBuilder, useBuilderState } from '../context';
import { useTranslator } from '../i18n';
import type { BlockTypeDefinition, StoredBlock } from '../types';
import { readFieldValue } from '../values';
import { AddBlockMenu } from './AddBlockMenu';
import styles from './BlockTreePane.module.css';
import { TrashIcon } from './icons';

/** The left pane: the block tree. Only the top level for now; nesting comes later. */
export function BlockTreePane({ headingId }: { headingId: string }) {
    const t = useTranslator();
    const blocks = useBuilderState((state) => state.entry.tree.blocks);

    return (
        <div className={styles.pane}>
            <h2 id={headingId} className={styles.heading}>
                {t('setono_sylius_cms.builder.blocks')}
            </h2>
            {blocks.length === 0 ? (
                <p className={styles.empty}>{t('setono_sylius_cms.builder.no_blocks')}</p>
            ) : (
                <ul className={styles.list}>
                    {blocks.map((block) => (
                        <li key={block.key}>
                            <BlockRow block={block} />
                        </li>
                    ))}
                </ul>
            )}
            <AddBlockMenu />
        </div>
    );
}

function BlockRow({ block }: { block: StoredBlock }) {
    const t = useTranslator();
    const { store } = useBuilder();
    const selected = useBuilderState((state) => state.selectedKey === block.key);
    const blockType = useBuilderState((state) => state.blockTypes.find((definition) => definition.code === block.type));
    const locale = useBuilderState((state) => state.locale);
    const hint = blockHint(block, blockType, locale);

    return (
        <div className={styles.row} data-selected={selected || undefined}>
            <button type="button" className={styles.select} aria-pressed={selected} onClick={() => store.getState().selectBlock(block.key)}>
                <span className={styles.label}>{blockType?.label ?? block.type}</span>
                {hint !== '' && <span className={styles.hint}>{hint}</span>}
            </button>
            <button
                type="button"
                className={styles.delete}
                aria-label={t('setono_sylius_cms.builder.delete_block')}
                title={t('setono_sylius_cms.builder.delete_block')}
                onClick={() => store.getState().removeBlock(block.key)}
            >
                <TrashIcon />
            </button>
        </div>
    );
}

/** The value of the block's first text field in the edited locale, to tell blocks of one type apart. */
function blockHint(block: StoredBlock, blockType: BlockTypeDefinition | undefined, locale: string): string {
    const field = blockType?.fields.find((definition) => definition.schema['x-cms-type'] === 'text');
    if (field === undefined) {
        return '';
    }

    const value = readFieldValue(block.fields, field.name, field.schema['x-cms-translatable'], locale);

    return typeof value === 'string' ? value : '';
}
