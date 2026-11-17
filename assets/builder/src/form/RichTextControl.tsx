import type { Editor, JSONContent } from '@tiptap/core';
import { Placeholder, UndoRedo } from '@tiptap/extensions';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslator, type MessageKey } from '../i18n';
import { useLinkTargets } from '../linkTargets';
import { LinkIcon } from '../shell/icons';
import { documentValue, headingLevels, richTextExtensions, type HeadingLevel } from '../richText/schema';
import type { JsonValue } from '../types';
import { isJsonObject, jsonEqual, plainText } from '../values';
import styles from './Form.module.css';
import { RichTextLinkPanel } from './RichTextLinkPanel';
import type { FieldControlProps } from './types';

/**
 * Rich text, edited with Tiptap and a toolbar for the schema's nodes and marks. An empty editor is no value, and shows
 * the text of its placeholder.
 */
export function RichTextControl({ id, value, onChange, schema, describedBy, placeholder }: FieldControlProps) {
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;
    const placeholderText = placeholder === undefined || placeholder === null ? '' : plainText(placeholder);
    const placeholderRef = useRef(placeholderText);
    placeholderRef.current = placeholderText;
    // Whether the panel that links the selected text is open
    const [linking, setLinking] = useState(false);

    const editor = useEditor({
        extensions: [...richTextExtensions, UndoRedo, Placeholder.configure({ placeholder: () => placeholderRef.current })],
        content: documentOf(value),
        editorProps: {
            attributes: {
                id,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': schema['x-cms-label'],
                ...(describedBy === undefined ? {} : { 'aria-describedby': describedBy }),
                ...(schema['x-cms-required'] ? { 'aria-required': 'true' } : {}),
                class: styles.richTextEditor ?? '',
            },
        },
        onUpdate: ({ editor: updated }) => onChangeRef.current(documentValue(updated)),
    });

    // A value changed elsewhere, such as the same field in another locale, replaces what the editor shows. It isn't an
    // edit, so it's neither reported nor undoable.
    useEffect(() => {
        if (!jsonEqual(normalized(value), documentValue(editor))) {
            editor.chain().setMeta('addToHistory', false).setContent(documentOf(value), { emitUpdate: false }).run();
        }
    }, [editor, value]);

    // The placeholder is drawn as a decoration, which a transaction redraws
    useEffect(() => {
        editor.view.dispatch(editor.state.tr.setMeta('addToHistory', false));
    }, [editor, placeholderText]);

    return (
        <div className={styles.richText}>
            <Toolbar editor={editor} controls={id} linking={linking} onLink={() => setLinking((open) => !open)} />
            {linking && <RichTextLinkPanel id={id} editor={editor} onClose={() => setLinking(false)} />}
            <EditorContent editor={editor} />
        </div>
    );
}

type Mark = 'bold' | 'italic' | 'underline' | 'strike' | 'code';

const marks: ReadonlyArray<{ mark: Mark; label: MessageKey; glyph: ReactNode }> = [
    { mark: 'bold', label: 'setono_sylius_cms.builder.rich_text.bold', glyph: <b>B</b> },
    { mark: 'italic', label: 'setono_sylius_cms.builder.rich_text.italic', glyph: <i>I</i> },
    { mark: 'underline', label: 'setono_sylius_cms.builder.rich_text.underline', glyph: <u>U</u> },
    { mark: 'strike', label: 'setono_sylius_cms.builder.rich_text.strike', glyph: <s>S</s> },
    { mark: 'code', label: 'setono_sylius_cms.builder.rich_text.code', glyph: <code>{'</>'}</code> },
];

interface ToolbarProps {
    editor: Editor;
    controls: string;
    /** Whether the panel that links the selected text is open. */
    linking: boolean;
    onLink: () => void;
}

function Toolbar({ editor, controls, linking, onLink }: ToolbarProps) {
    const t = useTranslator();
    // Without link targets, such as outside the builder, there's nothing to link to
    const linkable = useLinkTargets().length > 0;
    const active = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            code: current.isActive('code'),
            link: current.isActive('cmsLink'),
            selected: !current.state.selection.empty,
            heading: headingLevels.find((level) => current.isActive('heading', { level })),
            bulletList: current.isActive('bulletList'),
            orderedList: current.isActive('orderedList'),
            blockquote: current.isActive('blockquote'),
        }),
    });
    const run = (command: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => command(editor.chain().focus()).run();

    return (
        <div role="toolbar" aria-label={t('setono_sylius_cms.builder.rich_text.toolbar')} aria-controls={controls} className={styles.toolbar}>
            {marks.map(({ mark, label, glyph }) => (
                <ToolbarButton key={mark} label={t(label)} pressed={active[mark]} onClick={() => run((chain) => chain.toggleMark(mark))}>
                    {glyph}
                </ToolbarButton>
            ))}
            {linkable && (
                // Links the selected text, or changes the link the cursor is in
                <ToolbarButton label={t('setono_sylius_cms.builder.rich_text.link')} pressed={active.link} expanded={linking} disabled={!active.selected && !active.link && !linking} onClick={onLink}>
                    <LinkIcon />
                </ToolbarButton>
            )}
            <span className={styles.toolbarSeparator} aria-hidden="true" />
            {headingLevels.map((level: HeadingLevel) => (
                <ToolbarButton
                    key={level}
                    label={t('setono_sylius_cms.builder.rich_text.heading', { '%level%': String(level) })}
                    pressed={active.heading === level}
                    onClick={() => run((chain) => chain.toggleHeading({ level }))}
                >
                    H{level}
                </ToolbarButton>
            ))}
            <span className={styles.toolbarSeparator} aria-hidden="true" />
            <ToolbarButton label={t('setono_sylius_cms.builder.rich_text.bullet_list')} pressed={active.bulletList} onClick={() => run((chain) => chain.toggleBulletList())}>
                •
            </ToolbarButton>
            <ToolbarButton label={t('setono_sylius_cms.builder.rich_text.ordered_list')} pressed={active.orderedList} onClick={() => run((chain) => chain.toggleOrderedList())}>
                1.
            </ToolbarButton>
            <ToolbarButton label={t('setono_sylius_cms.builder.rich_text.blockquote')} pressed={active.blockquote} onClick={() => run((chain) => chain.toggleBlockquote())}>
                ”
            </ToolbarButton>
            <ToolbarButton label={t('setono_sylius_cms.builder.rich_text.horizontal_rule')} onClick={() => run((chain) => chain.setHorizontalRule())}>
                ―
            </ToolbarButton>
        </div>
    );
}

interface ToolbarButtonProps {
    label: string;
    /** Whether what it toggles is on where the cursor is. Left out for a button that only inserts. */
    pressed?: boolean;
    /** Whether the panel it opens is open. Left out for a button that opens none. */
    expanded?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}

function ToolbarButton({ label, pressed, expanded, disabled = false, onClick, children }: ToolbarButtonProps) {
    return (
        <button
            type="button"
            className={styles.toolbarButton}
            aria-label={label}
            title={label}
            aria-pressed={pressed}
            aria-expanded={expanded}
            disabled={disabled}
            // Keeps the editor's selection, which a click would otherwise take away
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function documentOf(value: JsonValue | undefined): JSONContent | null {
    return isJsonObject(value) ? (value as JSONContent) : null;
}


function normalized(value: JsonValue | undefined): JsonValue {
    return isJsonObject(value) ? value : null;
}
