/**
 * The one rich text schema (docs/spec/content-model.md#rich-text), shared by the right pane's editor and typing in
 * place in the preview, and matching the PHP schema that sanitises and renders it (src/RichText/RichTextSchema.php).
 * Only Tiptap's MIT core and MIT extensions.
 */
import { isNodeEmpty, Mark, type Attributes, type Editor, type Extensions } from '@tiptap/core';
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
import type { JsonValue } from '../types';

/** A link in rich text: a link without its label, with a plain string ref for every target, since rich text is kept per locale. */
export interface RichTextLink {
    target: string;
    ref: string;
    /** A fragment without the #, or null. */
    anchor: string | null;
}

/**
 * The plugin's own link mark, whose attributes are a link without its label. In the editor it's an <a> that leads
 * nowhere, carrying its attributes. The builder's preview renders each link with the same attributes, also one that
 * doesn't resolve, as a <span>, so typing in place reads every link back; any other <a> is plain text.
 */
export const CMSLink = Mark.create({
    name: 'cmsLink',
    // Typing at either end of a link doesn't extend it
    inclusive: false,

    addAttributes() {
        return {
            target: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-cms-link-target'),
                renderHTML: (attributes: Record<string, unknown>) => ({ 'data-cms-link-target': attributes.target }),
            },
            ref: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-cms-link-ref'),
                renderHTML: (attributes: Record<string, unknown>) => ({ 'data-cms-link-ref': attributes.ref }),
            },
            anchor: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-cms-link-anchor') || null,
                renderHTML: (attributes: Record<string, unknown>) => (typeof attributes.anchor === 'string' && attributes.anchor !== '' ? { 'data-cms-link-anchor': attributes.anchor } : {}),
            },
        };
    },

    parseHTML() {
        const getAttrs = (element: HTMLElement) => (element.getAttribute('data-cms-link-target') && element.getAttribute('data-cms-link-ref') ? null : false);

        return [
            { tag: 'a[data-cms-link-target]', getAttrs },
            { tag: 'span[data-cms-link-target]', getAttrs },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return ['a', HTMLAttributes, 0];
    },
});

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
    CMSLink,
];

/** Whether the editor holds nothing but empty blocks, whitespace and line breaks, which counts as no value. */
export function isEmptyDocument(editor: Editor): boolean {
    return isNodeEmpty(editor.state.doc, { ignoreWhitespace: true });
}

/** What a rich text field stores for the editor's content, in the right pane or typed in place: its document, or null when it's empty. */
export function documentValue(editor: Editor): JsonValue {
    return isEmptyDocument(editor) ? null : (editor.getJSON() as JsonValue);
}
