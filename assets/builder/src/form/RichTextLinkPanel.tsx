import { getMarkRange, type Editor } from '@tiptap/core';
import { useState, type KeyboardEvent } from 'react';
import { useTranslator } from '../i18n';
import { useLinkTargets } from '../linkTargets';
import { readLink, writeLink, type LinkChange } from '../links';
import type { RichTextLink } from '../richText/schema';
import type { JsonObject } from '../types';
import formStyles from './Form.module.css';
import styles from './LinkControl.module.css';
import { LinkTargetFields } from './LinkTargetFields';

export interface RichTextLinkPanelProps {
    id: string;
    editor: Editor;
    /** Closes the panel, whether the link was applied, removed or cancelled. */
    onClose: () => void;
}

/**
 * Links the selected text, or changes or removes the link the cursor is in, with the target picker a Link field has. A
 * link in rich text has no label: the text is its label. See "Rich text" in docs/spec/content-model.md
 */
export function RichTextLinkPanel({ id, editor, onClose }: RichTextLinkPanelProps) {
    const t = useTranslator();
    const linkTargets = useLinkTargets();
    const first = linkTargets[0]?.code ?? '';
    // What the panel links, kept while the editor types in it: the selection, or the whole link the cursor is in
    const [range] = useState(() => linkRange(editor));
    const [linked] = useState(() => editor.isActive('cmsLink'));
    const [value, setValue] = useState<JsonObject>(() => (linked ? linkValue(editor.getAttributes('cmsLink')) : { target: first, ref: '', anchor: null, label: null }));
    // Rich text is kept per locale, so a link holds one ref for every target, a URL too
    const link = readLink(value, 'shared', [], first);
    const change = (made: LinkChange): void => setValue(writeLink(value, 'shared', '', made, linkTargets, first));
    const applicable = link.ref.trim() !== '';

    const apply = (): void => {
        if (!applicable) {
            return;
        }

        const attributes: RichTextLink = { target: link.target, ref: link.ref.trim(), anchor: link.anchor === '' ? null : link.anchor };
        editor.chain().focus().setTextSelection(range).setMark('cmsLink', attributes).run();
        onClose();
    };

    const remove = (): void => {
        editor.chain().focus().setTextSelection(range).unsetMark('cmsLink').run();
        onClose();
    };

    const cancel = (): void => {
        editor.commands.focus();
        onClose();
    };

    // Enter in the URL or the anchor applies the link, and Esc cancels it, unless the search picker took the key
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.defaultPrevented) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            cancel();
        } else if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
            apply();
        }
    };

    return (
        <div role="group" aria-label={t('setono_sylius_cms.builder.rich_text.link')} className={`${styles.control} ${formStyles.linkPanel}`} onKeyDown={onKeyDown}>
            <div className={styles.part}>
                <label htmlFor={`${id}-link-target`} className={styles.label}>
                    {t('setono_sylius_cms.builder.rich_text.link_target')}
                </label>
                <LinkTargetFields id={`${id}-link-target`} offered={linkTargets} link={link} onChange={change} />
            </div>
            <div className={formStyles.linkPanelActions}>
                <button type="button" className={formStyles.button} disabled={!applicable} onClick={apply}>
                    {t('setono_sylius_cms.builder.rich_text.link_apply')}
                </button>
                {linked && (
                    <button type="button" className={formStyles.button} onClick={remove}>
                        {t('setono_sylius_cms.builder.rich_text.link_remove')}
                    </button>
                )}
                <button type="button" className={formStyles.button} onClick={cancel}>
                    {t('setono_sylius_cms.builder.rich_text.link_cancel')}
                </button>
            </div>
        </div>
    );
}

/** The selection, or the whole link the cursor is in when it's in one. */
function linkRange(editor: Editor): { from: number; to: number } {
    const { selection, schema } = editor.state;
    const type = schema.marks.cmsLink;
    const range = type === undefined || !editor.isActive('cmsLink') ? undefined : getMarkRange(selection.$from, type);

    return range ?? { from: selection.from, to: selection.to };
}

/** A link mark's attributes as a Link field's value, shared by every locale, with no label. */
function linkValue(attributes: Record<string, unknown>): JsonObject {
    return {
        target: typeof attributes.target === 'string' ? attributes.target : '',
        ref: typeof attributes.ref === 'string' ? attributes.ref : '',
        anchor: typeof attributes.anchor === 'string' ? attributes.anchor : null,
        label: null,
    };
}
