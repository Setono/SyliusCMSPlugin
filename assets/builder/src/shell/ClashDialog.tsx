import * as Dialog from '@radix-ui/react-dialog';
import { useId } from 'react';
import { useBuilder, useBuilderState } from '../context';
import { useTranslator, type Translate } from '../i18n';
import type { HeldClash } from '../pullIn';
import { findBlock } from '../tree';
import type { BlockTypeDefinition, JsonValue } from '../types';
import { isJsonObject } from '../values';
import styles from './ClashDialog.module.css';

/**
 * The clashes between the editor's save and a colleague's, one at a time: the editor keeps theirs or takes the other.
 * See "Saving" in docs/spec/builder.md
 */
export function ClashDialog() {
    const t = useTranslator();
    const { store } = useBuilder();
    const clashes = useBuilderState((state) => state.clashes);
    const blockTypes = useBuilderState((state) => state.blockTypes);
    const blocks = useBuilderState((state) => state.entry.tree.blocks);
    const resolved = useBuilderState((state) => state.resolvedClashes);
    const clash = clashes[0];

    if (clash === undefined) {
        return null;
    }

    const blockType = blockTypes.find(({ code }) => code === (clash.kind === 'deleted_block' ? clash.block.type : findBlock(blocks, clash.key)?.type));
    const index = String(resolved + 1);
    const count = String(resolved + clashes.length);

    return (
        <Dialog.Root open>
            <Dialog.Portal>
                <Dialog.Overlay className={styles.overlay} />
                <Dialog.Content className={styles.dialog} onEscapeKeyDown={(event) => event.preventDefault()} onPointerDownOutside={(event) => event.preventDefault()}>
                    <header className={styles.header}>
                        <Dialog.Title className={styles.title}>{t('setono_sylius_cms.builder.clash')}</Dialog.Title>
                        <span className={styles.count}>{t('setono_sylius_cms.builder.clash_count', { '%index%': index, '%count%': count })}</span>
                    </header>
                    <Dialog.Description className={styles.description}>{describe(clash, blockType, t)}</Dialog.Description>
                    {clash.kind === 'value' && (
                        <dl className={styles.values}>
                            <Side term={t('setono_sylius_cms.builder.clash_yours')} value={clash.mine} t={t} />
                            <Side term={t('setono_sylius_cms.builder.clash_theirs')} value={clash.theirs} t={t} />
                        </dl>
                    )}
                    <div className={styles.actions}>
                        <button type="button" className={styles.secondary} onClick={() => store.getState().resolveClash('theirs')}>
                            {t('setono_sylius_cms.builder.take_theirs')}
                        </button>
                        <button type="button" className={styles.primary} onClick={() => store.getState().resolveClash('mine')}>
                            {t('setono_sylius_cms.builder.keep_mine')}
                        </button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function Side({ term, value, t }: { term: string; value: JsonValue | null; t: Translate }) {
    const id = useId();

    return (
        <div className={styles.side}>
            <dt id={id} className={styles.term}>
                {term}
            </dt>
            <dd aria-labelledby={id} className={styles.value}>
                {value === null ? <em>{t('setono_sylius_cms.builder.clash_empty')}</em> : plainText(value)}
            </dd>
        </div>
    );
}

function describe(clash: HeldClash, blockType: BlockTypeDefinition | undefined, t: Translate): string {
    const block = blockType?.label ?? '';

    switch (clash.kind) {
        case 'value': {
            const field = blockType?.fields.find(({ name }) => name === clash.field)?.schema['x-cms-label'] ?? clash.field;

            return t('setono_sylius_cms.builder.clash_value', { '%field%': field, '%block%': block });
        }
        case 'deleted_block':
            return t('setono_sylius_cms.builder.clash_deleted_block', { '%block%': block });
        case 'changed_block':
            return t('setono_sylius_cms.builder.clash_changed_block', { '%block%': block });
    }
}

/** A value as text: the text of rich text, and anything else as it's written. */
export function plainText(value: JsonValue): string {
    if (typeof value === 'string') {
        return value;
    }

    if (isJsonObject(value) && value.type === 'doc') {
        return textOf(value).trim();
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function textOf(node: JsonValue | undefined): string {
    if (!isJsonObject(node)) {
        return '';
    }

    if (typeof node.text === 'string') {
        return node.text;
    }

    const children = Array.isArray(node.content) ? node.content.map(textOf).join('') : '';

    return node.type === 'paragraph' || node.type === 'heading' ? `${children}\n` : children;
}
