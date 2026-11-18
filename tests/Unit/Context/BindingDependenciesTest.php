<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\StoredValueBindings;
use Setono\SyliusCMSPlugin\Block\Button;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Context\BindingDependencies;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\LinkHandler;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductPage;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A binding that reads a context key depends on the entry providing it, so it counts toward a snippet's context check.
 * See "Snippets and their context" in docs/spec/slots.md
 */
final class BindingDependenciesTest extends TestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const BUTTON = '0193a5be-0000-7000-8000-000000000002';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-000000000003';

    /**
     * @test
     */
    public function it_finds_a_bound_field_a_bound_link_and_a_rich_text_token_that_read_a_context_key_each_once(): void
    {
        $heading = new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('product.brand')]);
        $button = new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'url', 'ref' => self::binding('product.page'), 'anchor' => null, 'label' => ['en_US' => 'View']]]);
        $token = ['type' => 'cmsBinding', 'attrs' => ['property' => 'taxon.name', 'params' => [], 'fallback' => null]];
        $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$token, $token]]]];
        $richText = new StoredBlock(self::RICH_TEXT, 'rich_text', 1, fields: ['content' => ['en_US' => $document, 'da_DK' => $document]]);

        self::assertEquals([
            new ContextDependency(self::name('app.binding.brand', 'setono_sylius_cms.block_type.heading'), ['product'], block: self::HEADING),
            new ContextDependency(self::name('app.binding.product_page', 'setono_sylius_cms.block_type.button'), ['product'], block: self::BUTTON),
            new ContextDependency(self::name('taxon.name', 'setono_sylius_cms.block_type.rich_text'), ['taxon'], block: self::RICH_TEXT),
        ], [...self::finder()->findIn(self::snippet($heading, $button, $richText))]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_bindings_of_the_channel_or_the_customer_or_fields_without_one(): void
    {
        $snippet = self::snippet(
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('customer.first_name')]),
            new StoredBlock(self::BUTTON, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']]),
        );

        self::assertSame([], [...self::finder()->findIn($snippet)]);
    }

    /**
     * @return array{'$bind': string, '$params': array<string, mixed>, '$fallback': null}
     */
    private static function binding(string $property): array
    {
        return ['$bind' => $property, '$params' => [], '$fallback' => null];
    }

    private static function name(string|TranslatableMessage $binding, string $block): TranslatableMessage
    {
        $binding = str_starts_with((string) $binding, 'app.') ? new TranslatableMessage((string) $binding) : $binding;

        return new TranslatableMessage('setono_sylius_cms.context.binding', ['%binding%' => $binding, '%block%' => new TranslatableMessage($block)], 'validators');
    }

    private static function snippet(StoredBlock ...$blocks): Snippet
    {
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $snippet;
    }

    private static function finder(): BindingDependencies
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'button' => Button::class, 'rich_text' => RichText::class], new FieldReader());

        return new BindingDependencies(
            new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())),
            new ContentTypeRegistry(['snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet']]),
            new StoredValueBindings(new FieldHandlerLocator(new ServiceLocator([
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                LinkHandler::class => static fn (): LinkHandler => Links::handler(),
                RichTextHandler::class => Links::richTextHandler(...),
            ]))),
            Bindings::registry([new ProductBrand(), new ProductPage()]),
        );
    }
}
