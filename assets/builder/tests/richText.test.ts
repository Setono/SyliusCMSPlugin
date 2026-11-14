import { getSchema } from '@tiptap/core';
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

    it('should give nodes the attributes the PHP schema keeps, and marks none', () => {
        const attributes = Object.fromEntries(
            Object.entries(schema.nodes)
                .map(([name, type]) => [name, Object.keys(type.spec.attrs ?? {})] as const)
                .filter(([, names]) => names.length > 0),
        );

        expect(attributes).toEqual(shared.attributes);
        expect(Object.values(schema.marks).flatMap((type) => Object.keys(type.spec.attrs ?? {}))).toEqual([]);
    });

    it('should have the heading levels of the PHP schema', () => {
        expect(schema.nodes.heading?.spec.parseDOM?.map((rule) => ('tag' in rule ? rule.tag : undefined))).toEqual(shared.headingLevels.map((level) => `h${level}`));
    });
});
