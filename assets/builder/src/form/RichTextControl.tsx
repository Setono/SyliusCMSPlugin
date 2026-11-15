import type { Editor, JSONContent } from '@tiptap/core';
import { UndoRedo } from '@tiptap/extensions';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import { useEffect, useRef, type ReactNode } from 'react';
import { useTranslator, type MessageKey } from '../i18n';
import { headingLevels, isEmptyDocument, richTextExtensions, type HeadingLevel } from '../richText/schema';
import type { JsonValue } from '../types';
import { isJsonObject, jsonEqual } from '../values';
import styles from './Form.module.css';
import type { FieldControlProps } from './types';

/** Rich text, edited with Tiptap and a toolbar for the schema's nodes and marks. An empty editor is no value. */
export function RichTextControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const editor = useEditor({
        extensions: [...richTextExtensions, UndoRedo],
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
        onUpdate: ({ editor: updated }) => onChangeRef.current(valueOf(updated)),
    });

    // A value changed elsewhere, such as the same field in another locale, replaces what the editor shows. It isn't an
    // edit, so it's neither reported nor undoable.
    useEffect(() => {
        if (!jsonEqual(normalized(value), valueOf(editor))) {
            editor.chain().setMeta('addToHistory', false).setContent(documentOf(value), { emitUpdate: false }).run();
        }
    }, [editor, value]);

    return (
        <div className={styles.richText}>
            <Toolbar editor={editor} controls={id} />
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

function Toolbar({ editor, controls }: { editor: Editor; controls: string }) {
    const t = useTranslator();
    const active = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            code: current.isActive('code'),
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
    onClick: () => void;
    children: ReactNode;
}

function ToolbarButton({ label, pressed, onClick, children }: ToolbarButtonProps) {
    return (
        <button
            type="button"
            className={styles.toolbarButton}
            aria-label={label}
            title={label}
            aria-pressed={pressed}
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

/** What the field stores for the editor's content: its document, or null when it's empty. */
function valueOf(editor: Editor): JsonValue {
    return isEmptyDocument(editor) ? null : (editor.getJSON() as JsonValue);
}

function normalized(value: JsonValue | undefined): JsonValue {
    return isJsonObject(value) ? value : null;
}
