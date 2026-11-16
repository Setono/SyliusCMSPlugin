import * as Dialog from '@radix-ui/react-dialog';
import { useId } from 'react';
import type { Locale } from '../config';
import { useBuilder, useBuilderState } from '../context';
import { useTranslator, type Translate } from '../i18n';
import type { HeldClash } from '../pullIn';
import { findBlock } from '../tree';
import { localeOf } from '../locales';
import type { BlockTypeDefinition, JsonValue } from '../types';
import { plainText } from '../values';
import styles from './ClashDialog.module.css';

/**
 * The clashes between the editor's save and a colleague's, one at a time: the editor keeps theirs or takes the other.
 * See "Saving" in docs/spec/builder.md
 */
export function ClashDialog() {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const clashes = useBuilderState((state) => state.clashes);
    const blockTypes = useBuilderState((state) => state.blockTypes);
    const blocks = useBuilderState((state) => state.entry.tree.blocks);
    const resolved = useBuilderState((state) => state.resolvedClashes);
    const clash = clashes[0];

    if (clash === undefined) {
        return null;
    }

    const blockType = clash.kind === 'entry_value' ? undefined : blockTypes.find(({ code }) => code === (clash.kind === 'deleted_block' ? clash.block.type : findBlock(blocks, clash.key)?.type));
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
                    <Dialog.Description className={styles.description}>{describe(clash, blockType, locales, t)}</Dialog.Description>
                    {(clash.kind === 'value' || clash.kind === 'entry_value') && (
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

function describe(clash: HeldClash, blockType: BlockTypeDefinition | undefined, locales: readonly Locale[], t: Translate): string {
    const block = blockType?.label ?? '';

    switch (clash.kind) {
        case 'value': {
            const field = blockType?.fields.find(({ name }) => name === clash.field)?.schema['x-cms-label'] ?? clash.field;

            return t('setono_sylius_cms.builder.clash_value', { '%field%': field, '%block%': block });
        }
        case 'entry_value':
            return t('setono_sylius_cms.builder.clash_entry_value', {
                '%field%': entryFieldLabel(clash.field, t),
                '%locale%': clash.locale === null ? '' : localeOf(locales, clash.locale).name,
            });
        case 'deleted_block':
            return t('setono_sylius_cms.builder.clash_deleted_block', { '%block%': block });
        case 'changed_block':
            return t('setono_sylius_cms.builder.clash_changed_block', { '%block%': block });
    }
}

/** The label of an entry field: a translation's title or slug. */
export function entryFieldLabel(field: string, t: Translate): string {
    switch (field) {
        case 'title':
            return t('setono_sylius_cms.builder.entry_title');
        case 'slug':
            return t('setono_sylius_cms.builder.slug');
        default:
            return field;
    }
}
