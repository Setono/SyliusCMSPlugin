<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\MisfitValue;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\OpeningHoursHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Store;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture\Wide;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Testimonial;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

final class MisfitValueTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const E = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e';

    private const EMPTY_DOCUMENT = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    /**
     * @test
     */
    public function it_finds_a_shared_value_that_does_not_fit_its_field_type_as_an_error_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(new StoredBlock(self::A, 'heading', 1, fields: ['level' => 'h6']), new StoredBlock(self::B, 'group', 1, fields: ['fullWidth' => 'yes']));

        self::assertEquals([
            self::problem($page, self::A, 'heading', 'level', 'setono_sylius_cms.field.heading.level', null),
            self::problem($page, self::B, 'group', 'fullWidth', 'setono_sylius_cms.field.group.full_width', null),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_translatable_value_that_does_not_fit_in_its_locale_and_one_that_is_no_locale_map_at_all(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 42, 'de_DE' => ['Hallo']]]),
            new StoredBlock(self::B, 'heading', 1, fields: ['text' => 'Hello']),
            new StoredBlock(self::C, 'heading', 1, fields: ['text' => ['Hello', 'Hej']]),
            new StoredBlock(self::D, 'rich_text', 1, fields: ['content' => ['en_US' => '<p>Hello</p>']]),
        );

        self::assertEquals([
            self::problem($page, self::A, 'heading', 'text', 'setono_sylius_cms.field.heading.text', 'da_DK'),
            self::problem($page, self::A, 'heading', 'text', 'setono_sylius_cms.field.heading.text', 'de_DE'),
            self::problem($page, self::B, 'heading', 'text', 'setono_sylius_cms.field.heading.text', null),
            self::problem($page, self::C, 'heading', 'text', 'setono_sylius_cms.field.heading.text', null),
            self::problem($page, self::D, 'rich_text', 'content', 'setono_sylius_cms.field.rich_text.content', 'en_US'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_checks_every_field_of_a_block_whatever_the_fields_before_it_hold(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'testimonial', 1, fields: ['quote' => 'Not a locale map', 'author' => 42, 'bio' => ['en_US' => '<p>Hello</p>']]),
            new StoredBlock(self::B, 'testimonial', 1, fields: ['author' => 'Jane', 'bio' => ['en_US' => 'Born in 1970', 7 => 'Under a key that is no locale']]),
        );

        self::assertEquals([
            self::problem($page, self::A, 'testimonial', 'quote', 'app.quote', null),
            self::problem($page, self::A, 'testimonial', 'author', 'app.author', null),
            self::problem($page, self::A, 'testimonial', 'bio', 'app.bio', 'en_US'),
            // The tree is stored as canonical JSON, whose keys are sorted
            self::problem($page, self::B, 'testimonial', 'bio', 'app.bio', '7'),
            self::problem($page, self::B, 'testimonial', 'bio', 'app.bio', 'en_US'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_values_at_any_depth_but_none_inside_a_block_of_a_type_the_code_no_longer_has(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'group', 1, children: [new StoredBlock(self::B, 'heading', 1, fields: ['level' => 1])]),
            new StoredBlock(self::C, 'promo', 1, fields: ['level' => 1], children: [new StoredBlock(self::D, 'heading', 1, fields: ['level' => 1])]),
            new StoredBlock(self::E, 'heading', 1, fields: ['level' => 2]),
        );

        self::assertEquals([
            self::problem($page, self::B, 'heading', 'level', 'setono_sylius_cms.field.heading.level', null),
            self::problem($page, self::E, 'heading', 'level', 'setono_sylius_cms.field.heading.level', null),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_checks_the_value_of_a_field_type_that_does_not_check_values_itself_against_the_type_its_schema_names(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'store', 1, fields: ['openingHours' => 'always']),
            new StoredBlock(self::B, 'store', 1, fields: ['openingHours' => ['mon' => '9-17']]),
        );

        self::assertEquals(
            [self::problem($page, self::A, 'store', 'openingHours', 'app.opening_hours', null)],
            [...$this->kind()->findIn($page)],
        );
    }

    /**
     * @test
     */
    public function it_finds_a_value_of_the_layout_that_does_not_fit_while_the_code_has_the_layout(): void
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(new StoredLayout('wide', 1, ['fullWidth' => 'no']), []));
        $removed = new Page();
        $removed->setBlockTree(new StoredTree(new StoredLayout('standard', 1, ['fullWidth' => 'no']), []));

        self::assertEquals([new Problem(
            'misfit_value',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.misfit_layout_value', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.group.full_width')]),
            Fix::inBuilder($page),
            null,
            'fullWidth',
        )], [...$this->kind()->findIn($page)]);
        self::assertSame([], [...$this->kind()->findIn($removed)]);
        self::assertSame([], [...$this->kind(['page.wide'])->findIn($page)], 'The default layout an entry in a disabled one renders in ignores its values');
    }

    /**
     * @test
     */
    public function it_never_counts_a_missing_translation_or_a_missing_or_empty_value_as_a_problem(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => '', 'de_DE' => null]]),
            new StoredBlock(self::B, 'heading', 1, fields: ['text' => [], 'level' => '']),
            new StoredBlock(self::C, 'rich_text', 1, fields: ['content' => ['en_US' => self::EMPTY_DOCUMENT]]),
            new StoredBlock(self::D, 'heading', 1),
        );

        self::assertSame([], [...$this->kind()->findIn($page)]);
    }

    private static function problem(Page $page, string $block, string $type, string $field, string $label, ?string $locale): Problem
    {
        $blockLabels = ['heading' => 'setono_sylius_cms.block_type.heading', 'group' => 'setono_sylius_cms.block_type.group', 'rich_text' => 'setono_sylius_cms.block_type.rich_text', 'store' => 'app.store', 'testimonial' => 'app.testimonial'];

        return new Problem(
            'misfit_value',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.misfit_value', ['%field%' => new TranslatableMessage($label), '%block%' => new TranslatableMessage($blockLabels[$type])]),
            Fix::inBuilder($page, $block, $locale),
            $block,
            $field,
            $locale,
        );
    }

    private static function page(StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    /**
     * @param list<string> $disabledLayouts
     */
    private function kind(array $disabledLayouts = []): MisfitValue
    {
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            RichTextHandler::class => Links::richTextHandler(...),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new Translator('en')),
            OpeningHoursHandler::class => static fn (): OpeningHoursHandler => new OpeningHoursHandler(),
        ]));

        return new MisfitValue(
            new BlockTypeRegistry(['heading' => Heading::class, 'group' => Group::class, 'rich_text' => RichText::class, 'store' => Store::class, 'testimonial' => Testimonial::class], new FieldReader()),
            new LayoutRegistry(['page' => ['wide' => Wide::class]], new FieldReader(), disabled: $disabledLayouts),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
            new FallbackValues($handlers, $this->prophesize(FallbackChainInterface::class)->reveal()),
        );
    }
}
