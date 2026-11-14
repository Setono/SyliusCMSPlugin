/**
 * The one rich text schema (docs/spec/content-model.md#rich-text), shared by the right pane's editor and typing in
 * place in the preview, and matching the PHP schema that sanitises and renders it (src/RichText/RichTextSchema.php).
 * Only Tiptap's MIT core and MIT extensions.
 */
import { isNodeEmpty, type Attributes, type Editor, type Extensions } from '@tiptap/core';
import { Blockquote } from '@tiptap/extension-blockquote';
import { Bold } from '@tiptap/extension-bold';
import { Code } from '@tiptap/extension-code';
import { Document } from '@tiptap/extension-document';
import { HardBreak } from '@tiptap/extension-hard-break';
import { Heading } from '@tiptap/extension-heading';
import { HorizontalRule } from '@tiptap/extension-horizontal-rule';
import { Italic } from '@tiptap/extension-italic';
import { BulletList, ListItem, OrderedList } from '@tiptap/extension-list';
import { Paragraph } from '@tiptap/extension-paragraph';
import { Strike } from '@tiptap/extension-strike';
import { Text } from '@tiptap/extension-text';
import { Underline } from '@tiptap/extension-underline';

/** A page's own headings are Heading blocks, so rich text starts at level 2. */
export const headingLevels = [2, 3, 4] as const;

export type HeadingLevel = (typeof headingLevels)[number];

/** The nodes and marks of the schema. An editor adds what it needs on top, such as undo. */
export const richTextExtensions: Extensions = [
    Document,
    Paragraph,
    Text,
    Heading.configure({ levels: [...headingLevels] }),
    BulletList,
    // Without the list style type Tiptap adds, which the shop doesn't render.
    OrderedList.extend({
        addAttributes() {
            const inherited: Attributes = this.parent?.() ?? {};
            const { type, ...attributes } = inherited;

            return attributes;
        },
    }),
    ListItem,
    Blockquote,
    HardBreak,
    HorizontalRule,
    Bold,
    Italic,
    Underline,
    Strike,
    Code,
];

/** Whether the editor holds nothing but empty blocks, whitespace and line breaks, which counts as no value. */
export function isEmptyDocument(editor: Editor): boolean {
    return isNodeEmpty(editor.state.doc, { ignoreWhitespace: true });
}
