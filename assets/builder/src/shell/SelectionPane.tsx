import { useBuilder, useBuilderState } from '../context';
import { FieldRow } from '../form/FieldRow';
import { findBlock } from '../tree';
import { readFieldValue } from '../values';
import styles from './SelectionPane.module.css';
import { SettingsPane } from './SettingsPane';

/** The right pane: the selected block's fields, rendered from their schemas, or the entry's settings. */
export function SelectionPane() {
    const { store } = useBuilder();
    const block = useBuilderState((state) => (state.selectedKey === null ? undefined : findBlock(state.entry.tree.blocks, state.selectedKey)));
    const blockType = useBuilderState((state) => state.blockTypes.find((definition) => definition.code === block?.type));
    const locale = useBuilderState((state) => state.locale);

    // With nothing selected, or the entry's settings selected, it shows the settings
    if (block === undefined) {
        return <SettingsPane />;
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
