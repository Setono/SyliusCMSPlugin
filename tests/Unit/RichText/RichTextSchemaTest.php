<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\RichText;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;

/**
 * See "Rich text" in docs/spec/content-model.md
 */
final class RichTextSchemaTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_the_nodes_marks_and_attributes_the_builders_editor_shares(): void
    {
        /** @var array{nodes: list<string>, marks: list<string>, attributes: array<string, list<string>>, markAttributes: array<string, list<string>>, headingLevels: list<int>} $shared */
        $shared = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixture/rich-text-schema.json'), true, 512, \JSON_THROW_ON_ERROR);
        $schema = new RichTextSchema();

        self::assertEqualsCanonicalizing($shared['nodes'], $schema->nodes());
        self::assertEqualsCanonicalizing($shared['marks'], $schema->marks());
        self::assertEquals($shared['attributes'], $schema->attributes());
        self::assertEquals($shared['markAttributes'], $schema->markAttributes());
        self::assertSame($shared['headingLevels'], RichTextSchema::HEADING_LEVELS);
    }

    /**
     * @test
     */
    public function it_drops_an_unknown_node_with_everything_in_it_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(
            ['type' => 'paragraph', 'content' => [self::text('Kept'), ['type' => 'mention', 'attrs' => ['id' => 'jane']]]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'paragraph', 'content' => [self::text('Dropped')]]]]]],
        ));

        self::assertSame(self::doc(['type' => 'paragraph', 'content' => [self::text('Kept')]]), $sanitized);
    }

    /**
     * @test
     */
    public function it_drops_an_unknown_mark_when_sanitising_and_keeps_its_text_and_the_known_marks(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Hello', [['type' => 'bold'], ['type' => 'highlight', 'attrs' => ['color' => 'red']], ['type' => 'italic']]),
            self::text(' world', [['type' => 'textStyle']]),
        ]]));

        self::assertSame(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Hello', [['type' => 'bold'], ['type' => 'italic']]),
            self::text(' world'),
        ]]), $sanitized);
    }

    /**
     * @test
     */
    public function it_keeps_a_link_s_target_ref_and_anchor_when_sanitising_and_drops_its_other_attributes(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Red shirt', [['type' => 'cmsLink', 'attrs' => ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'anchor' => 'sizes', 'href' => 'javascript:alert(1)', 'onclick' => 'alert(1)']], ['type' => 'bold']]),
            self::text(' and '),
            self::text('our story', [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => '/en_US/story']]]),
        ]]));

        self::assertSame(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Red shirt', [['type' => 'cmsLink', 'attrs' => ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'anchor' => 'sizes']], ['type' => 'bold']]),
            self::text(' and '),
            self::text('our story', [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => '/en_US/story', 'anchor' => null]]]),
        ]]), $sanitized);
    }

    /**
     * @test
     */
    public function it_drops_a_link_without_a_target_or_a_ref_when_sanitising_and_keeps_its_text(): void
    {
        $schema = new RichTextSchema();

        foreach ([
            'no attributes' => ['type' => 'cmsLink'],
            'attributes that are not a map' => ['type' => 'cmsLink', 'attrs' => 'product'],
            'no target' => ['type' => 'cmsLink', 'attrs' => ['ref' => 'T_SHIRT_RED', 'anchor' => null]],
            'an empty target' => ['type' => 'cmsLink', 'attrs' => ['target' => '', 'ref' => 'T_SHIRT_RED']],
            'no ref' => ['type' => 'cmsLink', 'attrs' => ['target' => 'product', 'anchor' => null]],
            'an empty ref' => ['type' => 'cmsLink', 'attrs' => ['target' => 'product', 'ref' => '']],
            'a ref per locale, which rich text never has' => ['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => ['en_US' => '/en_US/story']]],
            'a target that is not a string' => ['type' => 'cmsLink', 'attrs' => ['target' => 42, 'ref' => 'T_SHIRT_RED']],
        ] as $case => $link) {
            self::assertSame(
                self::doc(['type' => 'paragraph', 'content' => [self::text('Red shirt', [['type' => 'italic']])]]),
                $schema->sanitize(self::doc(['type' => 'paragraph', 'content' => [self::text('Red shirt', [$link, ['type' => 'italic']])]])),
                $case,
            );
        }
    }

    /**
     * @test
     */
    public function it_keeps_a_link_without_its_anchor_when_the_anchor_is_empty_or_not_a_string(): void
    {
        $schema = new RichTextSchema();

        foreach (['', 42, ['sizes'], null] as $anchor) {
            self::assertSame(
                self::doc(['type' => 'paragraph', 'content' => [self::text('Shirts', [['type' => 'cmsLink', 'attrs' => ['target' => 'taxon', 'ref' => 'T_SHIRTS', 'anchor' => null]]])]]),
                $schema->sanitize(self::doc(['type' => 'paragraph', 'content' => [self::text('Shirts', [['type' => 'cmsLink', 'attrs' => ['target' => 'taxon', 'ref' => 'T_SHIRTS', 'anchor' => $anchor]]])]])),
            );
        }
    }

    /**
     * @test
     */
    public function it_drops_a_level_1_heading_when_sanitising_and_keeps_its_text_in_a_paragraph(): void
    {
        $schema = new RichTextSchema();

        self::assertSame(
            self::doc(['type' => 'paragraph', 'content' => [self::text('Title')]]),
            $schema->sanitize(self::doc(['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [self::text('Title')]])),
        );
        self::assertSame(
            self::doc(['type' => 'paragraph']),
            $schema->sanitize(self::doc(['type' => 'heading'])),
            'A heading without a level is a level-1 heading in Tiptap',
        );
    }

    /**
     * @test
     *
     * @dataProvider provideHeadingLevels
     */
    public function it_keeps_a_heading_of_level_2_to_4_when_sanitising(int $level): void
    {
        $heading = self::doc(['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [self::text('Title')]]);

        self::assertSame($heading, (new RichTextSchema())->sanitize($heading));
    }

    /**
     * @return iterable<array{int}>
     */
    public function provideHeadingLevels(): iterable
    {
        yield [2];
        yield [3];
        yield [4];
    }

    /**
     * @test
     */
    public function it_drops_a_heading_whose_level_is_not_a_level_when_sanitising(): void
    {
        $schema = new RichTextSchema();

        foreach ([5, '2', 2.0, null] as $level) {
            self::assertSame(self::doc(['type' => 'paragraph']), $schema->sanitize(self::doc(['type' => 'heading', 'attrs' => ['level' => $level]])));
        }
    }

    /**
     * @test
     */
    public function it_drops_unknown_attributes_and_attribute_values_that_do_not_fit_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center', 'onclick' => 'alert(1)'], 'content' => [self::text('Hi')]],
            ['type' => 'heading', 'attrs' => ['level' => 3, 'id' => 'title'], 'content' => [self::text('Title')]],
            ['type' => 'orderedList', 'attrs' => ['start' => 3, 'type' => 'a'], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]],
            ['type' => 'orderedList', 'attrs' => ['start' => '3'], 'content' => [['type' => 'listItem']]],
            ['type' => 'horizontalRule', 'attrs' => []],
            ['type' => 'blockquote', 'attrs' => 'not attributes'],
        ));

        self::assertSame(self::doc(
            ['type' => 'paragraph', 'content' => [self::text('Hi')]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [self::text('Title')]],
            ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]],
            ['type' => 'orderedList', 'content' => [['type' => 'listItem']]],
            ['type' => 'horizontalRule'],
            ['type' => 'blockquote'],
        ), $sanitized);
    }

    /**
     * @test
     */
    public function it_sanitises_a_value_that_is_not_a_document_to_null(): void
    {
        $schema = new RichTextSchema();

        self::assertNull($schema->sanitize('<p>Hello</p>'));
        self::assertNull($schema->sanitize(null));
        self::assertNull($schema->sanitize(['type' => 'paragraph', 'content' => []]));
        self::assertNull($schema->sanitize(['content' => []]));
    }

    /**
     * @test
     */
    public function it_gives_a_document_without_a_list_of_content_an_empty_one_when_sanitising(): void
    {
        $schema = new RichTextSchema();

        self::assertSame(self::doc(), $schema->sanitize(['type' => 'doc']));
        self::assertSame(self::doc(), $schema->sanitize(['type' => 'doc', 'content' => 'Hello']));
    }

    /**
     * @test
     */
    public function it_drops_malformed_nodes_and_keys_a_node_does_not_have_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(
            ['type' => 'paragraph', 'id' => 'p1', 'content' => [
                ['type' => 'text', 'text' => ''],
                ['type' => 'text', 'text' => 42],
                ['type' => 'text'],
                ['type' => 'text', 'text' => 'Kept', 'html' => '<script></script>'],
                'not a node',
                ['type' => 42],
                ['text' => 'No type'],
                ['type' => 'hardBreak', 'marks' => [['type' => 'bold'], 'not a mark', ['type' => 42]]],
            ]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [self::text('Nested')]]]],
            ['type' => 'blockquote', 'content' => 'not a list'],
        ));

        self::assertSame(self::doc(
            ['type' => 'paragraph', 'content' => [self::text('Kept'), ['type' => 'hardBreak', 'marks' => [['type' => 'bold']]]]],
            ['type' => 'blockquote', 'content' => []],
        ), $sanitized);
    }

    /**
     * @test
     *
     * @dataProvider provideRenderedNodes
     *
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     */
    public function it_renders_each_node_and_mark_to_html(array $document, string $html): void
    {
        self::assertSame($html, (new RichTextSchema())->render($document));
    }

    /**
     * @return iterable<string, array{array{type: 'doc', content: list<array<string, mixed>>}, string}>
     */
    public function provideRenderedNodes(): iterable
    {
        $paragraph = static fn (array ...$content): array => ['type' => 'paragraph', 'content' => array_values($content)];
        $item = static fn (string $text): array => ['type' => 'listItem', 'content' => [$paragraph(self::text($text))]];

        yield 'doc' => [self::doc(), ''];
        yield 'paragraph and text' => [self::doc($paragraph(self::text('Hello')), $paragraph()), '<p>Hello</p><p></p>'];
        yield 'text is escaped' => [self::doc($paragraph(self::text('<b>Tom & Jerry</b> "say" \'it\''))), '<p>&lt;b&gt;Tom &amp; Jerry&lt;/b&gt; &quot;say&quot; &#039;it&#039;</p>'];
        yield 'heading' => [self::doc(
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::text('Two')]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [self::text('Three')]],
            ['type' => 'heading', 'attrs' => ['level' => 4], 'content' => [self::text('Four')]],
        ), '<h2>Two</h2><h3>Three</h3><h4>Four</h4>'];
        yield 'bulletList and listItem' => [self::doc(['type' => 'bulletList', 'content' => [$item('One'), $item('Two')]]), '<ul><li><p>One</p></li><li><p>Two</p></li></ul>'];
        yield 'orderedList' => [self::doc(['type' => 'orderedList', 'content' => [$item('One')]]), '<ol><li><p>One</p></li></ol>'];
        yield 'orderedList with a start' => [self::doc(['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [$item('Three')]]), '<ol start="3"><li><p>Three</p></li></ol>'];
        yield 'blockquote' => [self::doc(['type' => 'blockquote', 'content' => [$paragraph(self::text('Quoted'))]]), '<blockquote><p>Quoted</p></blockquote>'];
        yield 'hardBreak' => [self::doc($paragraph(self::text('One'), ['type' => 'hardBreak'], self::text('Two'))), '<p>One<br>Two</p>'];
        yield 'horizontalRule' => [self::doc($paragraph(self::text('Above')), ['type' => 'horizontalRule'], $paragraph(self::text('Below'))), '<p>Above</p><hr><p>Below</p>'];
        yield 'bold' => [self::doc($paragraph(self::text('a '), self::text('bold', [['type' => 'bold']]), self::text(' word'))), '<p>a <strong>bold</strong> word</p>'];
        yield 'italic' => [self::doc($paragraph(self::text('italic', [['type' => 'italic']]))), '<p><em>italic</em></p>'];
        yield 'underline' => [self::doc($paragraph(self::text('underlined', [['type' => 'underline']]))), '<p><u>underlined</u></p>'];
        yield 'strike' => [self::doc($paragraph(self::text('struck', [['type' => 'strike']]))), '<p><s>struck</s></p>'];
        yield 'code' => [self::doc($paragraph(self::text('$x', [['type' => 'code']]))), '<p><code>$x</code></p>'];
        yield 'marks together' => [self::doc($paragraph(self::text('both', [['type' => 'bold'], ['type' => 'italic']]))), '<p><strong><em>both</em></strong></p>'];
        yield 'cmsLink, which nothing resolves without a resolver' => [self::doc($paragraph(self::text('a link', [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'https://example.com', 'anchor' => null]]]))), '<p>a link</p>'];
    }

    /**
     * @test
     *
     * @dataProvider provideEmptyDocuments
     *
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     */
    public function an_empty_document_counts_as_empty(array $document): void
    {
        self::assertTrue((new RichTextSchema())->isEmpty($document));
    }

    /**
     * @return iterable<string, array{array{type: 'doc', content: list<array<string, mixed>>}}>
     */
    public function provideEmptyDocuments(): iterable
    {
        yield 'no content' => [self::doc()];
        yield 'an empty paragraph, as an empty editor has' => [self::doc(['type' => 'paragraph'])];
        yield 'empty paragraphs and headings' => [self::doc(['type' => 'paragraph', 'content' => []], ['type' => 'heading', 'attrs' => ['level' => 2]])];
        yield 'only whitespace and line breaks' => [self::doc(['type' => 'paragraph', 'content' => [self::text(" \t "), ['type' => 'hardBreak'], self::text("\u{a0}", [['type' => 'bold']])]])];
        yield 'an empty list in a quote' => [self::doc(['type' => 'blockquote', 'content' => [['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]]]])];
    }

    /**
     * @test
     *
     * @dataProvider provideDocumentsWithContent
     *
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     */
    public function a_document_with_text_or_a_horizontal_rule_is_not_empty(array $document): void
    {
        self::assertFalse((new RichTextSchema())->isEmpty($document));
    }

    /**
     * @return iterable<string, array{array{type: 'doc', content: list<array<string, mixed>>}}>
     */
    public function provideDocumentsWithContent(): iterable
    {
        yield 'text' => [self::doc(['type' => 'paragraph', 'content' => [self::text('Hi')]])];
        yield 'text after an empty paragraph' => [self::doc(['type' => 'paragraph'], ['type' => 'paragraph', 'content' => [self::text(' '), self::text('a')]])];
        yield 'text deep in a list' => [self::doc(['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [self::text('1')]]]]]])];
        yield 'a horizontal rule' => [self::doc(['type' => 'horizontalRule'])];
    }

    /**
     * @test
     */
    public function it_keeps_a_binding_token_with_its_property_parameters_fallback_and_marks_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Made of '),
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.attribute', 'params' => ['attribute' => 'material'], 'fallback' => 'cotton', 'colour' => 'red'], 'marks' => [['type' => 'bold']], 'content' => [self::text('x')]],
        ]]));

        self::assertSame(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Made of '),
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.attribute', 'params' => ['attribute' => 'material'], 'fallback' => 'cotton'], 'marks' => [['type' => 'bold']]],
        ]]), $sanitized);
    }

    /**
     * @test
     */
    public function it_gives_a_binding_token_empty_parameters_as_a_map_and_an_empty_fallback_as_none_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(['type' => 'paragraph', 'content' => [
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.name', 'params' => ['material'], 'fallback' => '']],
        ]]));

        self::assertEquals(self::doc(['type' => 'paragraph', 'content' => [
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.name', 'params' => new \stdClass(), 'fallback' => null]],
        ]]), $sanitized);
        self::assertSame('{"content":[{"content":[{"attrs":{"fallback":null,"params":{},"property":"product.name"},"type":"cmsBinding"}],"type":"paragraph"}],"type":"doc"}', CanonicalJson::encode($sanitized));
    }

    /**
     * @test
     */
    public function it_drops_a_binding_token_that_names_no_bindable_property_when_sanitising(): void
    {
        $sanitized = (new RichTextSchema())->sanitize(self::doc(['type' => 'paragraph', 'content' => [
            self::text('Hi'),
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'name', 'params' => [], 'fallback' => 'there']],
            ['type' => 'cmsBinding'],
        ]]));

        self::assertSame(self::doc(['type' => 'paragraph', 'content' => [self::text('Hi')]]), $sanitized);
    }

    /**
     * @test
     */
    public function it_counts_a_document_with_only_a_binding_token_as_not_empty(): void
    {
        $schema = new RichTextSchema();

        self::assertFalse($schema->isEmpty(self::doc(['type' => 'paragraph', 'content' => [['type' => 'cmsBinding', 'attrs' => ['property' => 'product.name', 'params' => [], 'fallback' => null]]]])));
    }

    /**
     * @test
     */
    public function it_renders_a_binding_token_as_the_text_it_resolves_to_or_else_its_fallback(): void
    {
        $document = self::doc(['type' => 'paragraph', 'content' => [
            self::text('Buy '),
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.name', 'params' => [], 'fallback' => 'this product'], 'marks' => [['type' => 'bold']]],
            self::text(' for '),
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.price', 'params' => [], 'fallback' => 'a good price']],
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'customer.first_name', 'params' => [], 'fallback' => null]],
        ]]);
        $resolved = ['product.name' => 'Fish & Chips', 'product.price' => null, 'customer.first_name' => null];

        $html = (new RichTextSchema())->render($document, resolveBinding: static fn (string $property, array $params): ?string => $resolved[$property]);

        self::assertSame('<p>Buy <strong>Fish &amp; Chips</strong> for a good price</p>', $html);
    }

    /**
     * @test
     */
    public function it_renders_a_binding_token_with_its_attributes_and_not_editable_when_marked(): void
    {
        $document = self::doc(['type' => 'paragraph', 'content' => [
            ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.attribute', 'params' => ['attribute' => 'material'], 'fallback' => 'cotton']],
        ]]);

        $html = (new RichTextSchema())->render($document, marked: true, resolveBinding: static fn (): ?string => 'Wool');

        self::assertSame('<p><span data-cms-binding="product.attribute" data-cms-binding-params="{&quot;attribute&quot;:&quot;material&quot;}" data-cms-binding-fallback="cotton" contenteditable="false">Wool</span></p>', $html);
    }

    /**
     * @test
     */
    public function it_lists_the_binding_tokens_of_a_document_in_document_order(): void
    {
        $bindings = (new RichTextSchema())->bindings(self::doc(
            ['type' => 'paragraph', 'content' => [['type' => 'cmsBinding', 'attrs' => ['property' => 'product.name', 'params' => [], 'fallback' => null]]]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.attribute', 'params' => ['attribute' => 'fit'], 'fallback' => 'regular']],
            ]]]]]],
        ));

        self::assertEquals([new StoredBinding('product.name'), new StoredBinding('product.attribute', ['attribute' => 'fit'], 'regular')], $bindings);
    }

    /**
     * @param array<string, mixed> ...$content
     *
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    private static function doc(array ...$content): array
    {
        return ['type' => 'doc', 'content' => array_values($content)];
    }

    /**
     * @param list<array<string, mixed>> $marks
     *
     * @return array<string, mixed>
     */
    private static function text(string $text, array $marks = []): array
    {
        return [] === $marks ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks];
    }
}
