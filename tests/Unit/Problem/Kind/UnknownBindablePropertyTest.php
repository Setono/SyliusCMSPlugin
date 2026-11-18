<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\StoredValueBindings;
use Setono\SyliusCMSPlugin\Block\Button;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\LinkHandler;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBindableProperty;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture\Tagline;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A field of a block or of the layout bound to a property the code no longer declares shows its fallback, and the
 * admin flags it. See "Bindings" in docs/spec/slots.md and "Problems" in docs/spec/admin.md
 */
final class UnknownBindablePropertyTest extends TestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const BUTTON = '0193a5be-0000-7000-8000-000000000002';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-000000000003';

    private const KNOWN = '0193a5be-0000-7000-8000-000000000004';

    /**
     * @test
     */
    public function it_finds_a_bound_field_a_bound_link_and_rich_text_tokens_of_a_property_the_code_no_longer_has_as_warnings_fixed_in_the_builder_at_the_block(): void
    {
        $token = ['type' => 'cmsBinding', 'attrs' => ['property' => 'product.stock', 'params' => [], 'fallback' => null]];
        $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$token, ['type' => 'text', 'text' => ' and '], $token]]]];
        $page = self::page(
            null,
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('product.material')]),
            new StoredBlock(self::KNOWN, 'heading', 1, fields: ['text' => self::binding('product.brand')]),
            new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'url', 'ref' => self::binding('product.page'), 'anchor' => null, 'label' => ['en_US' => 'View']]]),
            new StoredBlock(self::RICH_TEXT, 'rich_text', 1, fields: ['content' => ['en_US' => $document, 'da_DK' => $document]]),
        );

        self::assertEquals([
            self::problem($page, self::HEADING, 'setono_sylius_cms.block_type.heading', 'text', 'setono_sylius_cms.field.heading.text', 'product.material', null),
            self::problem($page, self::BUTTON, 'setono_sylius_cms.block_type.button', 'link', 'setono_sylius_cms.field.button.link', 'product.page', null),
            self::problem($page, self::RICH_TEXT, 'setono_sylius_cms.block_type.rich_text', 'content', 'setono_sylius_cms.field.rich_text.content', 'product.stock', 'da_DK'),
            self::problem($page, self::RICH_TEXT, 'setono_sylius_cms.block_type.rich_text', 'content', 'setono_sylius_cms.field.rich_text.content', 'product.stock', 'en_US'),
        ], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_fields_bound_to_properties_the_code_has_or_without_bindings(): void
    {
        $page = self::page(
            null,
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('product.brand')]),
            new StoredBlock(self::KNOWN, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']]),
        );

        self::assertSame([], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_field_of_the_layout_bound_to_a_property_the_code_no_longer_has(): void
    {
        $page = self::page(new StoredLayout('tagline', 1, ['tagline' => self::binding('product.material')]));

        self::assertEquals([new Problem(
            'unknown_bindable_property',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_layout_bindable_property', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.heading.text'), '%property%' => 'product.material']),
            Fix::inBuilder($page),
            null,
            'tagline',
        )], [...self::kind()->findIn($page)]);
    }

    private static function problem(Page $page, string $block, string $blockLabel, string $field, string $fieldLabel, string $property, ?string $locale): Problem
    {
        return new Problem(
            'unknown_bindable_property',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_bindable_property', [
                '%field%' => new TranslatableMessage($fieldLabel),
                '%block%' => new TranslatableMessage($blockLabel),
                '%property%' => $property,
            ]),
            Fix::inBuilder($page, $block, $locale),
            $block,
            $field,
            $locale,
        );
    }

    /**
     * @return array{'$bind': string, '$params': array<string, mixed>, '$fallback': null}
     */
    private static function binding(string $property): array
    {
        return ['$bind' => $property, '$params' => [], '$fallback' => null];
    }

    private static function page(?StoredLayout $layout, StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree($layout, array_values($blocks)));

        return $page;
    }

    private static function kind(): UnknownBindableProperty
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'button' => Button::class, 'rich_text' => RichText::class], new FieldReader());

        return new UnknownBindableProperty(
            new TreeValues($blockTypes, new LayoutRegistry(['page' => ['tagline' => Tagline::class]], new FieldReader())),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
            new StoredValueBindings(new FieldHandlerLocator(new ServiceLocator([
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                LinkHandler::class => static fn (): LinkHandler => Links::handler(),
                RichTextHandler::class => Links::richTextHandler(...),
            ]))),
            Bindings::registry([new ProductBrand()]),
        );
    }
}
