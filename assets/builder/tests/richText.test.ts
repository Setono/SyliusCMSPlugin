import { Editor, getSchema } from '@tiptap/core';
import { describe, expect, it } from 'vitest';
// The PHP schema's test reads the same file (tests/Unit/RichText/RichTextSchemaTest.php)
import shared from '../../../tests/Fixture/rich-text-schema.json';
import { richTextExtensions } from '../src/richText/schema';

describe('the rich text schema', () => {
    const schema = getSchema(richTextExtensions);

    it('should have the nodes and marks of the PHP schema', () => {
        expect(Object.keys(schema.nodes).sort()).toEqual([...shared.nodes].sort());
        expect(Object.keys(schema.marks).sort()).toEqual([...shared.marks].sort());
    });

    it('should give nodes and marks the attributes the PHP schema keeps', () => {
        const attributesOf = (types: Record<string, { spec: { attrs?: Record<string, unknown> | null | undefined } }>) =>
            Object.fromEntries(
                Object.entries(types)
                    .map(([name, type]) => [name, Object.keys(type.spec.attrs ?? {})] as const)
                    .filter(([, names]) => names.length > 0),
            );

        expect(attributesOf(schema.nodes)).toEqual(shared.attributes);
        expect(attributesOf(schema.marks)).toEqual(shared.markAttributes);
    });

    it("should read a link back from the preview's HTML, whether it resolves or not", () => {
        const editor = new Editor({
            extensions: richTextExtensions,
            content:
                '<p><a href="https://shop.example/en_US/products/red" data-cms-link-target="product" data-cms-link-ref="T_SHIRT_RED" data-cms-link-anchor="sizes">Red</a> and ' +
                '<span data-cms-link-target="entry" data-cms-link-ref="0193a5be-0000-7000-8000-000000000001">our team</span> and <a href="https://example.com">not ours</a></p>',
        });

        expect(editor.getJSON()).toEqual({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        { type: 'text', text: 'Red', marks: [{ type: 'cmsLink', attrs: { target: 'product', ref: 'T_SHIRT_RED', anchor: 'sizes' } }] },
                        { type: 'text', text: ' and ' },
                        { type: 'text', text: 'our team', marks: [{ type: 'cmsLink', attrs: { target: 'entry', ref: '0193a5be-0000-7000-8000-000000000001', anchor: null } }] },
                        { type: 'text', text: ' and not ours' },
                    ],
                },
            ],
        });
        editor.destroy();
    });

    it('should have the heading levels of the PHP schema', () => {
        expect(schema.nodes.heading?.spec.parseDOM?.map((rule) => ('tag' in rule ? rule.tag : undefined))).toEqual(shared.headingLevels.map((level) => `h${level}`));
    });
});
