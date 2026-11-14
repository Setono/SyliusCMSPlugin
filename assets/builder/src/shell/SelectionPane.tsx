import { useId } from 'react';
import { useBuilder, useBuilderState } from '../context';
import { FieldRow } from '../form/FieldRow';
import { useTranslator } from '../i18n';
import { findBlock } from '../tree';
import type { StoredBlock } from '../types';
import { readFieldValue } from '../values';
import { AddBlockButton } from './BlockPicker';
import { blockHint } from './BlockTreePane';
import { PlusIcon } from './icons';
import styles from './SelectionPane.module.css';

/** The right pane: the selected block's fields, rendered from their schemas, and its children. */
export function SelectionPane() {
    const t = useTranslator();
    const { store } = useBuilder();
    const block = useBuilderState((state) => (state.selectedKey === null ? undefined : findBlock(state.entry.tree.blocks, state.selectedKey)));
    const blockType = useBuilderState((state) => (block === undefined ? undefined : state.rules.blockTypes.get(block.type)));
    const locale = useBuilderState((state) => state.locale);

    if (block === undefined) {
        return (
            <div className={styles.pane}>
                <p className={styles.empty}>{t('setono_sylius_cms.builder.no_selection')}</p>
            </div>
        );
    }

    const label = blockType?.label ?? block.type;

    return (
        <div className={styles.pane}>
            <h2 className={styles.heading}>{label}</h2>
            <div key={block.key} className={styles.fields}>
                {blockType?.fields.map(({ name, schema }) => (
                    <FieldRow
                        key={name}
                        id={`cms-field-${block.key}-${name}`}
                        schema={schema}
                        locale={locale}
                        value={readFieldValue(block.fields, name, schema['x-cms-translatable'], locale)}
                        onChange={(value) => store.getState().setFieldValue(block.key, name, value)}
                    />
                ))}
            </div>
            {(blockType?.children.length ?? 0) > 0 && <Children block={block} label={label} />}
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
                                    <span className={styles.childLabel}>{childType?.label ?? child.type}</span>
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
