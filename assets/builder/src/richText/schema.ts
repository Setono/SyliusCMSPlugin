/**
 * The one rich text schema (docs/spec/content-model.md#rich-text), shared by the right pane's editor and typing in
 * place in the preview, and matching the PHP schema that sanitises and renders it (src/RichText/RichTextSchema.php).
 * Only Tiptap's MIT core and MIT extensions.
 */
import { isNodeEmpty, Mark, Node, type Attributes, type Editor, type Extensions } from '@tiptap/core';
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

/** A binding token's attributes: the property it shows, its parameters and the fallback for when it's empty. */
export interface RichTextBinding {
    property: string;
    params: Record<string, JsonValue>;
    fallback: string | null;
}

export interface CMSBindingOptions {
    /** What a token shows in the editor: its property's label. */
    label: (property: string) => string;
}

/**
 * The plugin's own binding token, an inline atom whose attributes are a binding. In the editor it shows its property's
 * label, and it can't be typed in. The builder's preview renders each token with the same attributes, so typing in
 * place reads every token back. See "Rich text" in docs/spec/content-model.md
 */
export const CMSBinding = Node.create<CMSBindingOptions>({
    name: 'cmsBinding',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addOptions() {
        return { label: (property: string) => property };
    },

    addAttributes() {
        return {
            property: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-cms-binding'),
                renderHTML: (attributes: Record<string, unknown>) => ({ 'data-cms-binding': attributes.property }),
            },
            params: {
                default: {},
                parseHTML: (element: HTMLElement) => parseParams(element.getAttribute('data-cms-binding-params')),
                renderHTML: (attributes: Record<string, unknown>) => ({ 'data-cms-binding-params': JSON.stringify(attributes.params ?? {}) }),
            },
            fallback: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-cms-binding-fallback') || null,
                renderHTML: (attributes: Record<string, unknown>) =>
                    typeof attributes.fallback === 'string' && attributes.fallback !== '' ? { 'data-cms-binding-fallback': attributes.fallback } : {},
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-cms-binding]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        const property = typeof node.attrs.property === 'string' ? node.attrs.property : '';

        return ['span', { ...HTMLAttributes, class: 'cms-binding-token', contenteditable: 'false' }, `⚡ ${this.options.label(property)}`];
    },

    renderText({ node }) {
        return typeof node.attrs.fallback === 'string' ? node.attrs.fallback : '';
    },
});

function parseParams(json: string | null): Record<string, JsonValue> {
    try {
        const parsed: unknown = JSON.parse(json ?? '{}');

        return typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed) ? (parsed as Record<string, JsonValue>) : {};
    } catch {
        return {};
    }
}

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
    CMSBinding,
];

/** Whether the editor holds nothing but empty blocks, whitespace and line breaks, which counts as no value. */
export function isEmptyDocument(editor: Editor): boolean {
    return isNodeEmpty(editor.state.doc, { ignoreWhitespace: true });
}

/** What a rich text field stores for the editor's content, in the right pane or typed in place: its document, or null when it's empty. */
export function documentValue(editor: Editor): JsonValue {
    return isEmptyDocument(editor) ? null : (editor.getJSON() as JsonValue);
}
