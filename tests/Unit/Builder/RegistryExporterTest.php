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
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\OpeningHoursHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Store;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Titled;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Wide;
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
        $contentTypes = $this->exporter()->export()['contentTypes'];

        self::assertSame(['code', 'label', 'blocks', 'layouts'], array_keys($contentTypes[0]));
        self::assertSame(['page', '[setono_sylius_cms.content_type.page]', [
            ['type' => 'columns', 'min' => null, 'max' => null],
            ['type' => 'heading', 'min' => null, 'max' => null],
        ]], [$contentTypes[0]['code'], $contentTypes[0]['label'], $contentTypes[0]['blocks']]);
        self::assertSame(['code' => 'recipe', 'label' => '[app.recipe]', 'blocks' => null, 'layouts' => []], $contentTypes[1]);
    }

    /**
     * @test
     */
    public function it_exports_the_layouts_of_each_content_type_with_their_field_schemas_and_the_default_and_disabled_ones_marked(): void
    {
        $contentTypes = $this->exporter(disabledLayouts: ['page.wide'])->export()['contentTypes'];

        self::assertSame([
            [
                'code' => 'landing',
                'label' => '[app.landing]',
                'version' => 2,
                'thumbnail' => null,
                'default' => true,
                'disabled' => false,
                'shows' => [],
                'fields' => [[
                    'name' => 'transparentHeader',
                    'schema' => [
                        'type' => 'boolean',
                        'default' => false,
                        'x-cms-type' => 'toggle',
                        'x-cms-label' => '[app.transparent_header]',
                        'x-cms-help' => null,
                        'x-cms-required' => false,
                        'x-cms-translatable' => false,
                    ],
                ]],
            ],
            [
                'code' => 'standard',
                'label' => '[setono_sylius_cms.layout.page.standard]',
                'version' => 1,
                'thumbnail' => '/build/app/standard.svg',
                'default' => false,
                'disabled' => false,
                'shows' => ['title'],
                'fields' => [[
                    'name' => 'showTitle',
                    'schema' => [
                        'type' => 'boolean',
                        'default' => true,
                        'x-cms-type' => 'toggle',
                        'x-cms-label' => '[app.show_title]',
                        'x-cms-help' => null,
                        'x-cms-required' => false,
                        'x-cms-translatable' => false,
                    ],
                ]],
            ],
            [
                'code' => 'wide',
                'label' => '[app.wide]',
                'version' => 1,
                'thumbnail' => null,
                'default' => false,
                'disabled' => true,
                'shows' => [],
                'fields' => [],
            ],
        ], $contentTypes[0]['layouts'], 'The default the config names comes first, marked, and a disabled layout is marked too');
    }

    /**
     * @test
     */
    public function it_exports_the_condition_kinds_with_their_parameters_as_fields_shared_by_all_translations(): void
    {
        /** @var array<string, array{code: string, label: string, fields: list<array{name: string, schema: array<string, mixed>}>}> $conditionKinds */
        $conditionKinds = array_column($this->exporter()->export()['conditionKinds'], null, 'code');

        self::assertSame(['locale_is', 'signed_in', 'date_window'], array_keys($conditionKinds));
        self::assertSame('[Locale is]', $conditionKinds['locale_is']['label']);
        self::assertSame([], $conditionKinds['signed_in']['fields']);
        self::assertSame([
            [
                'name' => 'from',
                'schema' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'x-cms-type' => 'date_time',
                    'x-cms-label' => '[setono_sylius_cms.field.date_window_condition.from]',
                    'x-cms-help' => null,
                    'x-cms-required' => false,
                    'x-cms-translatable' => false,
                ],
            ],
            [
                'name' => 'until',
                'schema' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'x-cms-type' => 'date_time',
                    'x-cms-label' => '[setono_sylius_cms.field.date_window_condition.until]',
                    'x-cms-help' => null,
                    'x-cms-required' => false,
                    'x-cms-translatable' => false,
                ],
            ],
        ], $conditionKinds['date_window']['fields']);
        self::assertSame('choice', $conditionKinds['locale_is']['fields'][0]['schema']['x-cms-type']);
    }

    /**
     * A parameter is shared by all translations, even with a field type that's translatable by default
     *
     * @test
     */
    public function it_exports_a_translatable_parameter_as_shared(): void
    {
        $exporter = $this->exporter(conditionKinds: ['titled' => new Titled()]);

        /** @var array<string, array{code: string, label: string, fields: list<array{name: string, schema: array<string, mixed>}>}> $conditionKinds */
        $conditionKinds = array_column($exporter->export()['conditionKinds'], null, 'code');

        self::assertFalse($conditionKinds['titled']['fields'][0]['schema']['x-cms-translatable']);
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
     * @param array<string, ConditionKindInterface> $conditionKinds condition kinds besides those of Conditions::registry()
     * @param list<string> $disabledLayouts
     */
    private function exporter(array $disabled = [], array $styles = [], array $blockTypes = [], array $conditionKinds = [], array $disabledLayouts = []): RegistryExporter
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
            DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
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
            Conditions::registry($conditionKinds),
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class, 'wide' => Wide::class]], new FieldReader(), ['page' => 'landing'], $disabledLayouts),
        );
    }
}
