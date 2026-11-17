import type { Editor } from '@tiptap/core';
import { useState, type KeyboardEvent } from 'react';
import { newBinding, propertyOf, TOKEN_TYPES, type StoredBinding } from '../bindings';
import { useOfferedProperties } from '../bindingsContext';
import { useTranslator } from '../i18n';
import type { RichTextBinding } from '../richText/schema';
import { BindingFallback, BindingParams } from './BindingParts';
import styles from './Bindings.module.css';
import formStyles from './Form.module.css';

export interface RichTextBindingPanelProps {
    id: string;
    editor: Editor;
    /** Closes the panel, whether a token was inserted or not. */
    onClose: () => void;
}

/**
 * Inserts a binding token at the cursor: a property of type text or number that fits the context the entry provides or
 * the snippet declares, its parameters, and the fallback it shows when it's empty. See "Bindings" in
 * docs/spec/builder.md
 */
export function RichTextBindingPanel({ id, editor, onClose }: RichTextBindingPanelProps) {
    const t = useTranslator();
    const offered = useOfferedProperties(TOKEN_TYPES);
    const [binding, setBinding] = useState<StoredBinding | null>(() => (offered[0] === undefined ? null : newBinding(offered[0].binding)));
    const [fallback, setFallback] = useState('');
    const property = binding === null ? undefined : propertyOf(offered, binding.$bind);

    const insert = (): void => {
        if (binding === null) {
            return;
        }

        const token: RichTextBinding = { property: binding.$bind, params: binding.$params, fallback: fallback.trim() === '' ? null : fallback.trim() };
        editor.chain().focus().insertContent({ type: 'cmsBinding', attrs: token }).run();
        onClose();
    };

    const cancel = (): void => {
        editor.commands.focus();
        onClose();
    };

    // Enter in the fallback inserts the token, and Esc cancels it
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Escape') {
            event.preventDefault();
            cancel();
        } else if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
            insert();
        }
    };

    return (
        <div role="group" aria-label={t('setono_sylius_cms.builder.rich_text.binding')} className={`${styles.panel} ${formStyles.linkPanel}`} onKeyDown={onKeyDown}>
            {offered.length === 0 ? (
                <p className={formStyles.help}>{t('setono_sylius_cms.builder.binding.none')}</p>
            ) : (
                <>
                    <div className={styles.part}>
                        <label htmlFor={`${id}-binding`} className={styles.partLabel}>
                            {t('setono_sylius_cms.builder.rich_text.binding_property')}
                        </label>
                        <select id={`${id}-binding`} className={formStyles.select} value={binding?.$bind ?? ''} onChange={(event) => setBinding(newBinding(event.currentTarget.value))}>
                            {offered.map((offeredProperty) => (
                                <option key={offeredProperty.binding} value={offeredProperty.binding}>
                                    {offeredProperty.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    {binding !== null && <BindingParams id={`${id}-binding`} property={property} binding={binding} locale="" onChange={setBinding} />}
                    <BindingFallback id={`${id}-binding-fallback`}>
                        <input
                            id={`${id}-binding-fallback`}
                            type="text"
                            className={formStyles.input}
                            value={fallback}
                            aria-describedby={`${id}-binding-fallback-help`}
                            onChange={(event) => setFallback(event.currentTarget.value)}
                        />
                    </BindingFallback>
                </>
            )}
            <div className={formStyles.linkPanelActions}>
                <button type="button" className={formStyles.button} disabled={binding === null} onClick={insert}>
                    {t('setono_sylius_cms.builder.rich_text.binding_insert')}
                </button>
                <button type="button" className={formStyles.button} onClick={cancel}>
                    {t('setono_sylius_cms.builder.rich_text.binding_cancel')}
                </button>
            </div>
        </div>
    );
}
