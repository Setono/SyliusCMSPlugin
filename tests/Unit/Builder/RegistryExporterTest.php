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
use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\DateHandler;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\EntriesHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\TaxonHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Media\MediaLimits;
use Setono\SyliusCMSPlugin\Media\MediaTypes;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\OpeningHoursHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Store;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture\Titled;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\OnProductPage;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ProductBadge;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\RatingHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Review;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Wide;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\NearbyShops;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Sylius\Component\Core\Model\ProductInterface;
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
                'needs' => [],
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
                            'x-cms-bindable' => ['text', 'number'],
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
    public function it_adds_the_x_cms_keywords_around_a_project_field_types_own_schema_with_the_binding_types_it_accepts(): void
    {
        $blockTypes = array_column($this->exporter(blockTypes: ['review' => Review::class])->export()['blockTypes'], null, 'code');
        self::assertIsArray($blockTypes['review']['fields']);
        $fields = array_column($blockTypes['review']['fields'], 'schema', 'name');

        self::assertSame([
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 5,
            'x-cms-options' => ['stars' => 5],
            'x-cms-type' => 'app_rating',
            'x-cms-control' => 'app-rating',
            'x-cms-label' => '[app.rating]',
            'x-cms-help' => null,
            'x-cms-required' => false,
            'x-cms-translatable' => false,
            'x-cms-bindable' => ['number'],
        ], $fields['rating']);
        self::assertIsArray($fields['quote']);
        self::assertSame(['text', 'number'], $fields['quote']['x-cms-bindable']);
        self::assertArrayNotHasKey('x-cms-binds-inside', $fields['quote'], 'A binding is its whole value');

        self::assertIsArray($blockTypes['heading']['fields']);
        $headingFields = array_column($blockTypes['heading']['fields'], 'schema', 'name');
        self::assertIsArray($headingFields['level']);
        self::assertArrayNotHasKey('x-cms-bindable', $headingFields['level'], 'A field type that accepts no binding leaves it out');
    }

    /**
     * @test
     */
    public function it_exports_the_bindable_properties_with_their_types_needs_and_parameter_fields_which_are_never_bound(): void
    {
        $properties = $this->exporter()->export()['bindableProperties'];

        self::assertSame(['product.brand', 'product.stock_level'], array_column($properties, 'binding'));
        self::assertSame([
            'binding' => 'product.brand',
            'label' => '[app.binding.brand]',
            'type' => 'text',
            'needs' => ['product'],
            'fields' => [],
        ], $properties[0]);
        self::assertSame('number', $properties[1]['type']);
        self::assertIsArray($properties[1]['fields']);
        self::assertSame(['includeReserved', 'warehouse'], array_column($properties[1]['fields'], 'name'));
        self::assertIsArray($properties[1]['fields'][0]);
        self::assertIsArray($properties[1]['fields'][0]['schema']);
        self::assertFalse($properties[1]['fields'][0]['schema']['x-cms-translatable']);
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
    public function it_exports_what_the_top_level_of_each_content_type_may_hold_its_url_prefix_and_whether_it_can_be_a_homepage(): void
    {
        $contentTypes = $this->exporter()->export()['contentTypes'];

        self::assertSame(['code', 'label', 'blocks', 'prefix', 'categoryType', 'homepage', 'provides', 'layouts', 'fields'], array_keys($contentTypes[0]));
        self::assertSame(['page', '[setono_sylius_cms.content_type.page]', [
            ['type' => 'columns', 'min' => null, 'max' => null],
            ['type' => 'heading', 'min' => null, 'max' => null],
        ], '', true], [$contentTypes[0]['code'], $contentTypes[0]['label'], $contentTypes[0]['blocks'], $contentTypes[0]['prefix'], $contentTypes[0]['homepage']]);
        self::assertSame(['code' => 'recipe', 'label' => '[app.recipe]', 'blocks' => null, 'prefix' => null, 'categoryType' => null, 'homepage' => false, 'provides' => ['entry'], 'layouts' => [], 'fields' => []], $contentTypes[1]);
    }

    /**
     * The entry settings render a content type's own fields from their schemas: those of its model shared by all
     * translations and those of its translations translatable, whatever their field types' defaults, and never bound
     *
     * @test
     */
    public function it_exports_the_schemas_of_a_content_types_own_fields_shared_or_translatable_where_they_sit(): void
    {
        /** @var array<string, array{fields: list<array{name: string, schema: array<string, mixed>}>}> $contentTypes */
        $contentTypes = array_column($this->exporter()->export()['contentTypes'], null, 'code');
        $fields = array_column($contentTypes['shop']['fields'], 'schema', 'name');

        self::assertSame(['city', 'openedOn', 'renovatedAt', 'parking', 'photo', 'directions'], array_keys($fields));
        self::assertSame(['type' => 'string', 'x-cms-options' => ['maxLength' => null], 'x-cms-type' => 'text', 'x-cms-label' => '[app.cms.field.city]', 'x-cms-help' => null, 'x-cms-required' => true, 'x-cms-translatable' => false], $fields['city']);
        self::assertTrue($fields['directions']['x-cms-translatable']);
        self::assertArrayNotHasKey('x-cms-bindable', $fields['directions']);
        self::assertFalse($fields['parking']['default']);
    }

    /**
     * @test
     */
    public function it_leaves_out_a_disabled_content_type(): void
    {
        self::assertSame(['page', 'recipe', 'snippet', 'location'], array_column($this->exporter(disabledContentTypes: ['shop'])->export()['contentTypes'], 'code'));
    }

    /**
     * An entry provides itself to its tree, and a snippet the context it declares, which the builder reads from it
     *
     * @test
     */
    public function it_exports_the_context_keys_an_entry_of_each_content_type_provides_or_none_for_a_snippet_which_declares_its_own(): void
    {
        /** @var array<string, array{provides: list<string>|null}> $contentTypes */
        $contentTypes = array_column($this->exporter()->export()['contentTypes'], null, 'code');

        self::assertSame(['page' => ['entry'], 'recipe' => ['entry'], 'snippet' => null, 'shop' => ['entry'], 'location' => ['entry']], array_map(static fn (array $contentType): ?array => $contentType['provides'], $contentTypes));
    }

    /**
     * @test
     */
    public function it_exports_the_context_keys_each_block_type_and_condition_kind_needs(): void
    {
        $registry = $this->exporter(blockTypes: ['product_badge' => ProductBadge::class], conditionKinds: ['on_product_page' => new OnProductPage()])->export();

        self::assertSame(['columns' => [], 'column' => [], 'heading' => [], 'product_badge' => ['product']], array_column($registry['blockTypes'], 'needs', 'code'));
        self::assertSame(['on_product_page' => ['product'], 'locale_is' => [], 'signed_in' => [], 'date_window' => []], array_column($registry['conditionKinds'], 'needs', 'code'));
    }

    /**
     * What the page scope editor on a snippet's overview page gets: only the kinds a page scope can hold, as the builder
     * gets them
     *
     * @test
     */
    public function it_exports_the_condition_kinds_that_read_the_host_page_and_the_context_keys_for_page_scopes(): void
    {
        $exporter = $this->exporter(conditionKinds: ['on_product_page' => new OnProductPage()]);
        $registry = $exporter->export();

        self::assertSame([
            'conditionKinds' => array_values(array_filter($registry['conditionKinds'], static fn (array $kind): bool => 'on_product_page' === $kind['code'])),
            'contextKeys' => $registry['contextKeys'],
        ], $exporter->exportForPageScopes());
        self::assertCount(1, $exporter->exportForPageScopes()['conditionKinds']);
    }

    /**
     * @test
     */
    public function it_exports_the_context_keys_with_their_labels_and_the_slots_with_the_keys_they_provide(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            ['key' => 'product', 'label' => '[setono_sylius_cms.context_key.product]'],
            ['key' => 'entry', 'label' => '[setono_sylius_cms.context_key.entry]'],
        ], $registry['contextKeys']);
        self::assertSame([
            ['identifier' => 'shop.footer', 'label' => '[setono_sylius_cms.slot.shop.footer]', 'provides' => []],
            ['identifier' => 'product.after_price', 'label' => '[setono_sylius_cms.slot.product.after_price]', 'provides' => ['product']],
        ], $registry['slots']);
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
        /** @var array<string, array{code: string, label: string, matches: string, matchesNot: string, fields: list<array{name: string, schema: array<string, mixed>}>}> $conditionKinds */
        $conditionKinds = array_column($this->exporter()->export()['conditionKinds'], null, 'code');

        self::assertSame(['locale_is', 'signed_in', 'date_window'], array_keys($conditionKinds));
        self::assertSame('[Locale is]', $conditionKinds['locale_is']['label']);
        // How the builder words a condition: "is one of", unless the kind words it otherwise, as a date window does
        self::assertSame(['[setono_sylius_cms.builder.is_one_of]', '[setono_sylius_cms.builder.is_not_one_of]'], [$conditionKinds['locale_is']['matches'] ?? null, $conditionKinds['locale_is']['matchesNot'] ?? null]);
        self::assertSame(['[setono_sylius_cms.builder.is_inside]', '[setono_sylius_cms.builder.is_outside]'], [$conditionKinds['date_window']['matches'] ?? null, $conditionKinds['date_window']['matchesNot'] ?? null]);
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
     * The builder checks a file before uploading it. See "What the library holds" in docs/spec/media.md
     *
     * @test
     */
    public function it_exports_the_effective_upload_limit_and_the_mime_types_of_each_kind_of_media_item(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            'maxUploadSize' => 2 * 1024 * 1024,
            'types' => ['image' => ['image/jpeg', 'image/png'], 'video' => [], 'document' => ['application/pdf']],
        ], $registry['media']);
    }

    /**
     * The link control offers them, a URL typed per locale and the others picked with the search under their code.
     * See "Link targets" in docs/spec/extending.md
     *
     * @test
     */
    public function it_exports_the_link_targets_in_the_order_they_are_registered(): void
    {
        self::assertSame([
            ['code' => 'url', 'label' => '[setono_sylius_cms.link_target.url]', 'translatableRef' => true],
            ['code' => 'app_store', 'label' => '[app.link_target.store]', 'translatableRef' => false],
            ['code' => 'recipe', 'label' => '[app.link_target.recipe]', 'translatableRef' => false],
        ], $this->exporter()->export()['linkTargets']);
    }

    /**
     * A collection's category type, by its code, whose entries From a collection's categories picker searches
     *
     * @test
     */
    public function it_exports_the_category_type_of_a_content_type_by_its_code(): void
    {
        $contentTypes = array_column($this->exporter()->export()['contentTypes'], 'categoryType', 'code');

        self::assertSame('recipe', $contentTypes['location'] ?? null);
        self::assertNull($contentTypes['shop'] ?? null);
    }

    /**
     * The "Filled by" select offers them, with their parameters filled in inline. A disabled source isn't offered, and
     * the lists it fills already keep resolving through it. See "Sources" in docs/spec/builder.md
     *
     * @test
     */
    public function it_exports_the_sources_editors_may_pick_with_their_parameters_as_shared_fields_but_not_a_disabled_one(): void
    {
        self::assertSame([
            [
                'code' => 'on_sale',
                'label' => '[app.source.on_sale]',
                'needs' => [],
                'entryContentTypes' => null,
                'fields' => [
                    [
                        'name' => 'taxon',
                        'schema' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'x-cms-type' => 'taxon',
                            'x-cms-label' => '[app.source.on_sale.taxon]',
                            'x-cms-help' => null,
                            'x-cms-required' => true,
                            'x-cms-translatable' => false,
                        ],
                    ],
                    [
                        'name' => 'onlyNew',
                        'schema' => [
                            'type' => 'boolean',
                            'default' => false,
                            'x-cms-type' => 'toggle',
                            'x-cms-label' => '[app.source.on_sale.only_new]',
                            'x-cms-help' => null,
                            'x-cms-required' => false,
                            'x-cms-translatable' => false,
                        ],
                    ],
                ],
            ],
            ['code' => 'product_taxons', 'label' => '[app.source.product_taxons]', 'needs' => ['product'], 'entryContentTypes' => null, 'fields' => []],
            // It fits only shops as the entry, so the builder offers it only there
            ['code' => 'nearby_shops', 'label' => '[app.source.nearby_shops]', 'needs' => ['entry'], 'entryContentTypes' => ['shop'], 'fields' => []],
        ], $this->exporter()->export()['sources']);
    }

    /**
     * @param list<string> $disabled
     * @param array<string, array<string, array{label: string, class: string}>> $styles
     * @param array<string, class-string> $blockTypes block types besides the shipped Columns, Column and Heading
     * @param array<string, ConditionKindInterface> $conditionKinds condition kinds besides those of Conditions::registry()
     * @param list<string> $disabledLayouts
     * @param list<string> $disabledContentTypes
     */
    private function exporter(array $disabled = [], array $styles = [], array $blockTypes = [], array $conditionKinds = [], array $disabledLayouts = [], array $disabledContentTypes = []): RegistryExporter
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

        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
            'location' => ['model' => Location::class, 'translation' => null, 'resource' => 'app.location'],
        ], disabled: $disabledContentTypes);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            EntriesHandler::class => static fn (): EntriesHandler => new EntriesHandler(Sources::listFieldHandler(), $contentTypes),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator->reveal()),
            OpeningHoursHandler::class => static fn (): OpeningHoursHandler => new OpeningHoursHandler(),
            RatingHandler::class => static fn (): RatingHandler => new RatingHandler(),
            DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            TaxonHandler::class => static fn (): TaxonHandler => new TaxonHandler(),
            DateHandler::class => static fn (): DateHandler => new DateHandler(),
            MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
        ]));

        $blockTypes = new BlockTypeRegistry(['columns' => Columns::class, 'column' => Column::class, 'heading' => Heading::class] + $blockTypes, new FieldReader(), $disabled, $styles);

        return new RegistryExporter(
            $blockTypes,
            $contentTypes,
            new NestingRules($blockTypes, $contentTypes),
            $handlers,
            $translator->reveal(),
            new UiStrings($translator->reveal(), $catalogues->reveal()),
            Conditions::registry($conditionKinds),
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class, 'wide' => Wide::class]], new FieldReader(), ['page' => 'landing'], $disabledLayouts),
            // A configured limit below PHP's
            new MediaLimits(new UploadLimit(2 * 1024 * 1024, 8 * 1024 * 1024), new MediaTypes(['image' => ['image/jpeg', 'image/png'], 'document' => ['application/pdf']])),
            Links::registry(),
            Sources::registry(['on_sale' => new OnSale(), 'clearance' => new Clearance(), 'product_taxons' => new ProductTaxons(), 'nearby_shops' => new NearbyShops()], ['clearance']),
            new ContextKeyRegistry([
                'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
                'entry' => ['class' => EntryInterface::class, 'label' => 'setono_sylius_cms.context_key.entry'],
            ]),
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            Bindings::registry([new ProductBrand(), new StockLevel()]),
        );
    }
}
