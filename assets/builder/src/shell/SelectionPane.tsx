import { useBuilder, useBuilderState } from '../context';
import { FieldRow } from '../form/FieldRow';
import { useTranslator } from '../i18n';
import { findBlock } from '../tree';
import { readFieldValue } from '../values';
import styles from './SelectionPane.module.css';

/** The right pane: the selected block's fields, rendered from their schemas. */
export function SelectionPane() {
    const t = useTranslator();
    const { store } = useBuilder();
    const block = useBuilderState((state) => (state.selectedKey === null ? undefined : findBlock(state.entry.tree.blocks, state.selectedKey)));
    const blockType = useBuilderState((state) => state.blockTypes.find((definition) => definition.code === block?.type));
    const locale = useBuilderState((state) => state.locale);

    if (block === undefined) {
        return (
            <div className={styles.pane}>
                <p className={styles.empty}>{t('setono_sylius_cms.builder.no_selection')}</p>
            </div>
        );
    }

    return (
        <div className={styles.pane}>
            <h2 className={styles.heading}>{blockType?.label ?? block.type}</h2>
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
        </div>
    );
}
