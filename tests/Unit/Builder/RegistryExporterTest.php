<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\RegistryExporter;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\OpeningHoursHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Store;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistryExporterTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_exports_the_block_types_with_their_field_schemas_in_the_current_locale(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            [
                'code' => 'heading',
                'label' => 'Overskrift',
                'version' => 1,
                'thumbnail' => '/bundles/setonosyliuscmsplugin/thumbnail/block/heading.svg',
                'disabled' => false,
                'parents' => null,
                'children' => [],
                'styles' => [],
                'fields' => [
                    [
                        'name' => 'text',
                        'schema' => [
                            'type' => 'string',
                            'x-cms-options' => ['maxLength' => null],
                            'x-cms-type' => 'text',
                            'x-cms-label' => '[setono_sylius_cms.field.heading.text]',
                            'x-cms-help' => null,
                            'x-cms-required' => true,
                            'x-cms-translatable' => true,
                        ],
                    ],
                    [
                        'name' => 'level',
                        'schema' => [
                            'type' => 'string',
                            'enum' => ['h1', 'h2', 'h3', 'h4'],
                            'x-cms-options' => [
                                'choices' => [
                                    'h1' => '[setono_sylius_cms.choice.heading_level.h1]',
                                    'h2' => '[setono_sylius_cms.choice.heading_level.h2]',
                                    'h3' => '[setono_sylius_cms.choice.heading_level.h3]',
                                    'h4' => '[setono_sylius_cms.choice.heading_level.h4]',
                                ],
                                'multiple' => false,
                            ],
                            'default' => 'h2',
                            'x-cms-type' => 'choice',
                            'x-cms-label' => '[setono_sylius_cms.field.heading.level]',
                            'x-cms-help' => null,
                            'x-cms-required' => false,
                            'x-cms-translatable' => false,
                        ],
                    ],
                ],
            ],
        ], array_values(array_filter($registry['blockTypes'], static fn (array $blockType): bool => 'heading' === $blockType['code'])));
    }

    /**
     * @test
     */
    public function it_exports_the_field_control_of_a_field_type_that_declares_one_and_leaves_it_out_for_one_that_does_not(): void
    {
        $blockTypes = array_column($this->exporter(blockTypes: ['store' => Store::class])->export()['blockTypes'], null, 'code');
        self::assertIsArray($blockTypes['store']['fields']);
        $fields = array_column($blockTypes['store']['fields'], 'schema', 'name');

        self::assertSame([
            'type' => 'object',
            'x-cms-type' => 'app_opening_hours',
            'x-cms-control' => 'app-opening-hours',
            'x-cms-label' => '[app.opening_hours]',
            'x-cms-help' => null,
            'x-cms-required' => false,
            'x-cms-translatable' => false,
        ], $fields['openingHours']);
        self::assertIsArray($fields['name']);
        self::assertSame('text', $fields['name']['x-cms-type']);
        self::assertArrayNotHasKey('x-cms-control', $fields['name']);
    }

    /**
     * @test
     */
    public function it_exports_what_each_block_type_may_hold_and_where_it_may_sit_by_code(): void
    {
        $blockTypes = array_column($this->exporter()->export()['blockTypes'], null, 'code');

        self::assertSame([['type' => 'column', 'min' => 2, 'max' => 4]], $blockTypes['columns']['children']);
        self::assertNull($blockTypes['columns']['parents']);
        self::assertSame(['columns'], $blockTypes['column']['parents']);
        self::assertSame([
            ['type' => 'columns', 'min' => null, 'max' => null],
            ['type' => 'heading', 'min' => null, 'max' => null],
        ], $blockTypes['column']['children']);
    }

    /**
     * @test
     */
    public function it_marks_a_disabled_block_type_so_the_picker_leaves_it_out_and_keeps_it_where_it_is_allowed(): void
    {
        $blockTypes = array_column($this->exporter(disabled: ['heading'])->export()['blockTypes'], null, 'code');

        self::assertTrue($blockTypes['heading']['disabled']);
        self::assertFalse($blockTypes['columns']['disabled']);
        self::assertIsArray($blockTypes['column']['children']);
        self::assertContains(['type' => 'heading', 'min' => null, 'max' => null], $blockTypes['column']['children']);
    }

    /**
     * @test
     */
    public function it_exports_the_block_styles_of_each_block_type_with_their_labels_in_the_current_locale(): void
    {
        $exporter = $this->exporter(styles: ['heading' => [
            'outlined' => ['label' => 'app.cms.block_style.outlined', 'class' => 'app-heading--outlined'],
            'shouting' => ['label' => 'app.cms.block_style.shouting', 'class' => 'app-heading--shouting'],
        ]]);

        $blockTypes = array_column($exporter->export()['blockTypes'], null, 'code');

        self::assertSame([
            ['code' => 'outlined', 'label' => '[app.cms.block_style.outlined]'],
            ['code' => 'shouting', 'label' => '[app.cms.block_style.shouting]'],
        ], $blockTypes['heading']['styles']);
        self::assertSame([], $blockTypes['columns']['styles']);
    }

    /**
     * @test
     */
    public function it_exports_what_the_top_level_of_each_content_type_may_hold(): void
    {
        self::assertSame([
            [
                'code' => 'page',
                'label' => '[setono_sylius_cms.content_type.page]',
                'blocks' => [
                    ['type' => 'columns', 'min' => null, 'max' => null],
                    ['type' => 'heading', 'min' => null, 'max' => null],
                ],
            ],
            ['code' => 'recipe', 'label' => '[app.recipe]', 'blocks' => null],
        ], $this->exporter()->export()['contentTypes']);
    }

    /**
     * @test
     */
    public function it_exports_the_builder_ui_strings_of_the_locale_and_its_fallbacks(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            'setono_sylius_cms.builder.saved' => 'Gemt',
            'setono_sylius_cms.builder.saving' => '[setono_sylius_cms.builder.saving]',
        ], $registry['strings']);
    }

    /**
     * @param list<string> $disabled
     * @param array<string, array<string, array{label: string, class: string}>> $styles
     * @param array<string, class-string> $blockTypes block types besides the shipped Columns, Column and Heading
     */
    private function exporter(array $disabled = [], array $styles = [], array $blockTypes = []): RegistryExporter
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::type('string'))->will(static fn (array $arguments): string => sprintf('[%s]', is_string($arguments[0] ?? null) ? $arguments[0] : ''));
        $translator->trans('setono_sylius_cms.block_type.heading')->willReturn('Overskrift');
        $translator->trans('setono_sylius_cms.builder.saved')->willReturn('Gemt')->shouldBeCalledOnce();

        $fallback = new MessageCatalogue('en', ['messages' => [
            'setono_sylius_cms.builder.saving' => 'Saving…',
            'setono_sylius_cms.builder.saved' => 'Saved',
            'sylius.ui.save' => 'Save',
            42 => 'A message with a numeric id',
        ]]);
        $catalogue = new MessageCatalogue('da', ['messages' => ['setono_sylius_cms.builder.saving' => 'Gemmer…', 'setono_sylius_cms.builder.saved' => 'Gemt']]);
        $catalogue->addFallbackCatalogue($fallback);

        $catalogues = $this->prophesize(TranslatorBagInterface::class);
        $catalogues->getCatalogue()->willReturn($catalogue);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator->reveal()),
            OpeningHoursHandler::class => static fn (): OpeningHoursHandler => new OpeningHoursHandler(),
        ]));

        $blockTypes = new BlockTypeRegistry(['columns' => Columns::class, 'column' => Column::class, 'heading' => Heading::class] + $blockTypes, new FieldReader(), $disabled, $styles);
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);

        return new RegistryExporter(
            $blockTypes,
            $contentTypes,
            new NestingRules($blockTypes, $contentTypes),
            $handlers,
            $translator->reveal(),
            $catalogues->reveal(),
        );
    }
}
