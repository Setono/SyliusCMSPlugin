<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductPage;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithRichText;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class RichTextHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const DOCUMENT = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'A '],
        ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' word'],
    ]]]];

    /**
     * @test
     */
    public function it_is_the_shipped_rich_text_field_type_translatable_by_default(): void
    {
        $fields = (new FieldReader())->read(WithRichText::class);

        self::assertSame('rich_text', $fields['content']->type->code);
        self::assertTrue($fields['content']->translatable);
        self::assertSame(RichTextHandler::class, $fields['content']->handler());
    }

    /**
     * @test
     */
    public function it_describes_a_prosemirror_document_in_its_schema(): void
    {
        self::assertSame(
            [
                'type' => 'object',
                'properties' => ['type' => ['const' => 'doc'], 'content' => ['type' => 'array']],
                'required' => ['type', 'content'],
            ],
            self::handler()->schema(new RichText(label: 'Content')),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_document_as_a_rich_text_value_with_its_html(): void
    {
        $value = self::handler()->hydrate(new RichText(label: 'Content'), self::DOCUMENT, $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame(self::DOCUMENT, $value->document);
        self::assertSame('<p>A <strong>bold</strong> word</p>', $value->html);
    }

    /**
     * @test
     */
    public function it_hydrates_what_the_schema_has_and_drops_the_rest(): void
    {
        $value = self::handler()->hydrate(new RichText(label: 'Content'), ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Title']]],
        ]], $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame('<p>Hi</p><p>Title</p>', $value->html);
    }

    /**
     * @test
     */
    public function it_hydrates_an_empty_document_and_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = self::handler();
        $field = new RichText(label: 'Content');

        self::assertNull($handler->hydrate($field, ['type' => 'doc', 'content' => [['type' => 'paragraph']]], $this->hydrationContext()));
        self::assertNull($handler->hydrate($field, '<p>Hello</p>', $this->hydrationContext()));
        self::assertNull($handler->hydrate($field, null, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_fits_a_document_even_an_empty_one_and_nothing_else(): void
    {
        $handler = self::handler();
        $field = new RichText(label: 'Content');

        self::assertTrue($handler->fits($field, self::DOCUMENT));
        self::assertTrue($handler->fits($field, ['type' => 'doc', 'content' => [['type' => 'paragraph']]]));
        self::assertFalse($handler->fits($field, '<p>Hello</p>'));
        self::assertFalse($handler->fits($field, ['type' => 'paragraph']));
    }

    /**
     * @test
     */
    public function it_renders_a_link_that_resolves_as_a_link_to_where_it_leads_with_its_anchor(): void
    {
        $references = new ReferenceMap(['product' => ['PANCAKES' => new Recipe('Pancakes', 'pancakes')]]);

        $value = self::handler()->hydrate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'Visit '],
            ['type' => 'text', 'text' => 'our store', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'copenhagen', 'anchor' => 'opening hours']]]],
            ['type' => 'text', 'text' => ' or bake '],
            ['type' => 'text', 'text' => 'the ', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'PANCAKES', 'anchor' => null]]]],
            ['type' => 'text', 'text' => 'pancakes', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'PANCAKES', 'anchor' => null]], ['type' => 'bold']]],
            ['type' => 'text', 'text' => ' and email ', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'us', 'marks' => [['type' => 'italic'], ['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'mailto:hello@example.com?subject="Hi"&body=<3', 'anchor' => null]]]],
        ), $this->hydrationContext(references: $references));

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame(
            '<p>Visit <a href="/en_US/stores/copenhagen#opening%20hours">our store</a> or bake <a href="/en_US/recipes/pancakes">the <strong>pancakes</strong></a>'
            . '<em> and email <a href="mailto:hello@example.com?subject=&quot;Hi&quot;&amp;body=&lt;3">us</a></em></p>',
            $value->html,
        );
    }

    /**
     * @test
     */
    public function it_renders_a_link_that_does_not_resolve_as_plain_text_and_keeps_its_other_marks(): void
    {
        $value = self::handler()->hydrate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'a store that closed', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'odense', 'anchor' => null]]]],
            ['type' => 'text', 'text' => ', a recipe that was deleted', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'WAFFLES', 'anchor' => null]], ['type' => 'bold']]],
            ['type' => 'text', 'text' => ', a target the code no longer has', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_gone', 'ref' => 'gone', 'anchor' => 'top']]]],
            ['type' => 'text', 'text' => ', a URL that leads nowhere', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'javascript:alert(1)', 'anchor' => null]]]],
        ), $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame('<p>a store that closed<strong>, a recipe that was deleted</strong>, a target the code no longer has, a URL that leads nowhere</p>', $value->html);
        self::assertSame(4, $value->unresolvedLinks);
    }

    /**
     * @test
     */
    public function it_counts_each_link_that_does_not_resolve_once_and_none_that_resolve(): void
    {
        $closed = ['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'odense', 'anchor' => null]];

        $value = self::handler()->hydrate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'a store that closed', 'marks' => [$closed]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'the same one', 'marks' => [$closed]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'one that is open', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'aarhus', 'anchor' => null]]]],
        ), $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame(1, $value->unresolvedLinks);
        self::assertSame(0, self::handler()->hydrate(new RichText(label: 'Content'), self::DOCUMENT, $this->hydrationContext())?->unresolvedLinks);
    }

    /**
     * @test
     */
    public function it_renders_each_link_with_its_attributes_in_the_builder_s_preview_and_one_that_does_not_resolve_as_a_span(): void
    {
        $document = self::paragraph(
            ['type' => 'text', 'text' => 'our store', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'copenhagen', 'anchor' => 'hours']]]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'the old one', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'odense', 'anchor' => null]]]],
        );

        $builder = self::handler()->hydrate(new RichText(label: 'Content'), $document, $this->hydrationContext(RenderContext::PREVIEW_MODE_BUILDER));
        $shared = self::handler()->hydrate(new RichText(label: 'Content'), $document, $this->hydrationContext(RenderContext::PREVIEW_MODE_SHARE));

        self::assertInstanceOf(RichTextValue::class, $builder);
        self::assertSame(
            '<p><a href="/en_US/stores/copenhagen#hours" data-cms-link-target="app_store" data-cms-link-ref="copenhagen" data-cms-link-anchor="hours">our store</a> and '
            . '<span data-cms-link-target="app_store" data-cms-link-ref="odense">the old one</span></p>',
            $builder->html,
        );
        self::assertInstanceOf(RichTextValue::class, $shared);
        self::assertSame('<p><a href="/en_US/stores/copenhagen#hours">our store</a> and the old one</p>', $shared->html, 'A share link renders like the shop');
    }

    /**
     * @test
     */
    public function it_refers_to_the_records_its_links_lead_to_each_once(): void
    {
        $references = self::handler()->references(new RichText(label: 'Content'), ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Pancakes', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'PANCAKES', 'anchor' => null]]]],
                ['type' => 'text', 'text' => ' and '],
                ['type' => 'text', 'text' => 'our store', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'copenhagen', 'anchor' => null]]]],
                ['type' => 'text', 'text' => ' and '],
                ['type' => 'text', 'text' => 'a site', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'https://example.com', 'anchor' => null]]]],
            ]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Pancakes again', 'marks' => [['type' => 'bold'], ['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'PANCAKES', 'anchor' => 'toppings']]]],
                ['type' => 'text', 'text' => 'Waffles', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'WAFFLES', 'anchor' => null]]]],
                ['type' => 'text', 'text' => 'Not a link', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe']]]],
            ]]]]]],
        ]]);

        self::assertEquals([new Reference('product', 'PANCAKES', false), new Reference('product', 'WAFFLES', false)], [...$references]);
    }

    /**
     * @test
     */
    public function it_holds_no_references_without_links_or_in_a_value_that_is_not_a_document(): void
    {
        self::assertSame([], [...self::handler()->references(new RichText(label: 'Content'), self::DOCUMENT)]);
        self::assertSame([], [...self::handler()->references(new RichText(label: 'Content'), '<p><a href="/">Home</a></p>')]);
        self::assertSame([], [...self::handler()->references(new RichText(label: 'Content'), null)]);
    }

    /**
     * @test
     */
    public function it_sanitises_a_document_before_it_is_saved(): void
    {
        self::assertSame(
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]],
            self::handler()->sanitize(new RichText(label: 'Content'), ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'attrs' => ['style' => 'color: red'], 'content' => [['type' => 'text', 'text' => 'Hi']]],
                ['type' => 'iframe', 'attrs' => ['src' => 'https://example.com']],
            ]]),
        );
    }

    /**
     * @test
     */
    public function it_saves_a_value_that_is_not_a_document_as_it_is_because_invalid_content_saves(): void
    {
        self::assertSame('<p>Hello</p>', self::handler()->sanitize(new RichText(label: 'Content'), '<p>Hello</p>'));
    }

    /**
     * @test
     */
    public function it_accepts_a_document_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        self::handler()->validate(new RichText(label: 'Content'), self::DOCUMENT, $context->reveal());
        self::handler()->validate(new RichText(label: 'Content'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_document(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_rich_text')->willReturn($builder->reveal())->shouldBeCalledOnce();

        self::handler()->validate(new RichText(label: 'Content'), '<p>Hello</p>', $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_link_whose_ref_its_target_refuses_once_and_accepts_the_others(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_link_url')->willReturn($builder->reveal())->shouldBeCalledOnce();
        $context->buildViolation(Argument::not('setono_sylius_cms.field.not_link_url'))->shouldNotBeCalled();

        self::handler()->validate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'our site', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'https://example.com', 'anchor' => null]]]],
            ['type' => 'text', 'text' => 'a store that closed', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'app_store', 'ref' => 'odense', 'anchor' => null]]]],
            ['type' => 'text', 'text' => 'a script', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'javascript:alert(1)', 'anchor' => null]]]],
            ['type' => 'text', 'text' => 'another script', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'url', 'ref' => 'javascript:alert(2)', 'anchor' => null]]]],
        ), $context->reveal());
    }

    /**
     * A token resolves like a bound field: see "Rich text" in docs/spec/content-model.md
     *
     * @test
     */
    public function it_renders_a_binding_token_as_what_it_resolves_to_from_the_host_page_s_object(): void
    {
        $handler = Links::richTextHandlerWith([new ProductBrand('Acme'), new StockLevel()]);
        $context = new HydrationContext(Rendering::context(hostPageContext: ['product' => new Product()]));

        $value = $handler->hydrate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'By '],
            self::token('product.brand', 'us'),
            ['type' => 'text', 'text' => ', '],
            self::token('product.stock_level'),
            ['type' => 'text', 'text' => ' left'],
        ), $context);

        self::assertSame('<p>By Acme, 7 left</p>', $value?->html);
    }

    /**
     * @test
     */
    public function it_renders_a_binding_token_whose_value_is_empty_as_its_fallback_or_else_as_nothing(): void
    {
        $handler = Links::richTextHandlerWith([new ProductBrand()]);

        $value = $handler->hydrate(new RichText(label: 'Content'), self::paragraph(
            ['type' => 'text', 'text' => 'By '],
            self::token('product.brand', 'us'),
            self::token('product.colour', 'nobody'),
            self::token('product.brand'),
        ), new HydrationContext(Rendering::context()));

        self::assertSame('<p>By usnobody</p>', $value?->html);
    }

    /**
     * Bound values can't be typed in place: see "Working in the preview" in docs/spec/builder.md
     *
     * @test
     */
    public function it_renders_a_binding_token_with_its_attributes_in_the_builder_s_preview(): void
    {
        $handler = Links::richTextHandlerWith([new ProductBrand('Acme')]);
        $context = new HydrationContext(Rendering::context(previewMode: RenderContext::PREVIEW_MODE_BUILDER, hostPageContext: ['product' => new Product()]));

        $value = $handler->hydrate(new RichText(label: 'Content'), self::paragraph(self::token('product.brand', 'us')), $context);

        self::assertSame('<p><span data-cms-binding="product.brand" data-cms-binding-params="{}" data-cms-binding-fallback="us" contenteditable="false">Acme</span></p>', $value?->html);
    }

    /**
     * @test
     */
    public function it_rejects_a_binding_token_of_a_property_the_code_no_longer_has_or_of_a_type_that_is_no_text_or_number(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setParameter(Argument::cetera())->willReturn($builder->reveal());
        $builder->addViolation()->shouldBeCalledTimes(2);

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.unknown_binding')->willReturn($builder->reveal())->shouldBeCalledOnce();
        $context->buildViolation('setono_sylius_cms.field.binding_not_accepted')->willReturn($builder->reveal())->shouldBeCalledOnce();

        Links::richTextHandlerWith([new ProductBrand(), new ProductPage()])->validate(new RichText(label: 'Content'), self::paragraph(
            self::token('product.brand'),
            self::token('product.colour'),
            self::token('product.page'),
        ), $context->reveal());
    }

    /**
     * @return array{type: 'cmsBinding', attrs: array{property: string, params: array<string, mixed>, fallback: string|null}}
     */
    private static function token(string $property, ?string $fallback = null): array
    {
        return ['type' => 'cmsBinding', 'attrs' => ['property' => $property, 'params' => [], 'fallback' => $fallback]];
    }

    private static function handler(): RichTextHandler
    {
        return Links::richTextHandler();
    }

    /**
     * @param array<string, mixed> ...$content
     *
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    private static function paragraph(array ...$content): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => array_values($content)]]];
    }

    /**
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     */
    private function hydrationContext(?string $previewMode = null, ReferenceMap $references = new ReferenceMap()): HydrationContext
    {
        return new HydrationContext(new RenderContext(
            $this->prophesize(ChannelInterface::class)->reveal(),
            'en_US',
            'USD',
            null,
            '@guest',
            new \DateTimeImmutable(),
            previewMode: $previewMode,
        ), $references);
    }
}
