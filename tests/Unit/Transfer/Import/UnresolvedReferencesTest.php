<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Field\EntryHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedEntry;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadEntry;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadExport;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadPreset;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReference;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReferences;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The references the records an import writes hold that don't resolve in the target: CMS UUIDs that are neither
 * exported nor present, and Sylius codes the target lacks, found in fields, bindings, visibility conditions, placements'
 * page scopes, settings and translations. They import unchanged, and the plan lists them. See "References that don't
 * resolve" in docs/spec/transfer.md
 */
final class UnresolvedReferencesTest extends TestCase
{
    use ProphecyTrait;

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const OTHER_PAGE = '0193a5be-71b1-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET_IN_EXPORT = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const SNIPPET_IN_TARGET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const SNIPPET_GONE = '0193a5be-dead-7000-8000-000000000001';

    private const ENTRY_GONE = '0193a5be-dead-7000-8000-000000000002';

    private const IMAGE_IN_EXPORT = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const IMAGE_IN_TARGET = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    private const IMAGE_GONE = '0193a5be-dead-7000-8000-000000000003';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const STARTING_POINT = '0193a5be-7401-7d3e-8f4a-5b6c7d8e9f0a';

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> */
    private ObjectProphecy $customerGroups;

    /** @var ObjectProphecy<RepositoryInterface<LocaleInterface>> */
    private ObjectProphecy $locales;

    /** @var ObjectProphecy<RepositoryInterface<ProductAttributeInterface>> */
    private ObjectProphecy $productAttributes;

    protected function setUp(): void
    {
        // What the target has
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy(Argument::type('array'))->will(self::withCodes(Channel::class, ['WEB']));
        $this->channels = $channels;
        /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $customerGroups */
        $customerGroups = $this->prophesize(RepositoryInterface::class);
        $customerGroups->findBy(Argument::type('array'))->will(self::withCodes(CustomerGroup::class, ['wholesale']));
        $this->customerGroups = $customerGroups;
        /** @var ObjectProphecy<RepositoryInterface<LocaleInterface>> $locales */
        $locales = $this->prophesize(RepositoryInterface::class);
        $locales->findBy(Argument::type('array'))->will(self::withCodes(Locale::class, ['en_US']));
        $this->locales = $locales;
        /** @var ObjectProphecy<RepositoryInterface<ProductAttributeInterface>> $productAttributes */
        $productAttributes = $this->prophesize(RepositoryInterface::class);
        $productAttributes->findBy(Argument::type('array'))->will(self::withCodes(ProductAttribute::class, ['material']));
        $this->productAttributes = $productAttributes;
    }

    /**
     * @test
     */
    public function it_lists_the_entries_and_media_items_an_entry_uses_that_are_neither_in_the_export_nor_in_the_target(): void
    {
        $page = self::entry(self::page(self::PAGE, 'Autumn sale', [
            self::snippetBlock(self::SNIPPET_IN_EXPORT),
            self::snippetBlock(self::SNIPPET_IN_TARGET),
            self::snippetBlock(self::SNIPPET_GONE),
            self::teaser(image: self::IMAGE_IN_EXPORT),
            // A link to an entry doesn't bring it along, but it's a reference all the same
            self::teaser(image: self::IMAGE_GONE, readMore: self::ENTRY_GONE),
            self::teaser(image: self::IMAGE_IN_TARGET),
        ]));
        $snippet = self::entry(self::snippet(self::SNIPPET_IN_EXPORT, 'Autumn banner'), picked: false);
        $image = self::mediaItem(self::IMAGE_IN_EXPORT, MediaKind::Image);

        $unresolved = $this->unresolvedReferences()->in(self::export([$page, $snippet], mediaItems: [$image]), [$page], []);

        self::assertSame([
            [self::PAGE, 'Autumn sale', 'entry', self::SNIPPET_GONE],
            [self::PAGE, 'Autumn sale', 'media_item', self::IMAGE_GONE],
            [self::PAGE, 'Autumn sale', 'entry', self::ENTRY_GONE],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_lists_the_channels_customer_groups_and_locales_of_an_entrys_settings_and_translations_the_target_lacks(): void
    {
        $content = self::page(self::PAGE, 'Autumn sale');
        $content->setCurrentLocale('xx_XX');
        $content->setTitle('Herbstverkauf');
        $page = self::entry($content, new EntrySettings(
            true,
            new DateWindow(),
            ['WEB', 'GONE_WEB'],
            Audience::only('wholesale', '@guest', '@no_group', 'gone_group'),
            ['GONE_HOME'],
        ));
        // In the export, but the import doesn't write it
        $leftAlone = self::entry(self::page(self::OTHER_PAGE, 'Spring sale'), new EntrySettings(true, new DateWindow(), ['OTHER_GONE'], Audience::everyone()), picked: false);
        // The reserved values are no customer groups to look up
        $this->customerGroups->findBy(['code' => ['gone_group', 'wholesale']])->shouldBeCalledOnce()->will(self::withCodes(CustomerGroup::class, ['wholesale']));

        $unresolved = $this->unresolvedReferences()->in(self::export([$page, $leftAlone]), [$page], []);

        self::assertSame([
            [self::PAGE, 'Autumn sale', 'channel', 'GONE_WEB'],
            [self::PAGE, 'Autumn sale', 'channel', 'GONE_HOME'],
            [self::PAGE, 'Autumn sale', 'customer_group', 'gone_group'],
            [self::PAGE, 'Autumn sale', 'locale', 'xx_XX'],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_lists_the_products_of_blocks_visibility_conditions_and_of_a_snippets_placements_page_scopes_the_target_lacks(): void
    {
        $snippet = self::entry(self::snippet(self::SNIPPET_IN_EXPORT, 'Autumn banner', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'teaser', 1, conditions: [self::productCondition('gone_jeans'), self::productCondition('jeans')], children: [
                new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'teaser', 1, conditions: [self::productCondition('gone_shirt')]),
            ]),
            // A condition of a kind the code no longer has holds nothing
            new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'teaser', 1, conditions: [['kind' => 'removed', 'negate' => false, 'params' => ['product' => 'gone_removed']]]),
        ]), placements: [
            new ExportedPlacement('0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e', 'product.after_price', [self::productCondition('gone_hat'), self::productCondition('jeans')], []),
        ]);

        $unresolved = $this->unresolvedReferences()->in(self::export([$snippet]), [$snippet], []);

        self::assertSame([
            [self::SNIPPET_IN_EXPORT, 'Autumn banner', 'product', 'gone_jeans'],
            [self::SNIPPET_IN_EXPORT, 'Autumn banner', 'product', 'gone_shirt'],
            [self::SNIPPET_IN_EXPORT, 'Autumn banner', 'product', 'gone_hat'],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_counts_the_references_of_a_type_no_loader_checks_as_resolved(): void
    {
        $snippet = self::entry(self::snippet(self::SNIPPET_IN_EXPORT, 'Autumn banner', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'teaser', 1, conditions: [self::productCondition('gone_jeans')]),
        ]));

        self::assertSame([], $this->unresolvedReferences(withProducts: false)->in(self::export([$snippet]), [$snippet], []));
    }

    /**
     * @test
     */
    public function it_lists_the_product_attributes_the_bindings_of_an_entrys_tree_show_that_the_target_lacks(): void
    {
        $content = self::page(self::PAGE, 'Autumn sale', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1, fields: [
                'text' => self::attributeBinding('material'),
                // A translatable field binds in each locale
                'title' => ['en_US' => self::attributeBinding('gone_fit'), 'da_DK' => self::attributeBinding('gone_care')],
                // Not the Product attribute binding, or without an attribute
                'subtitle' => ['$bind' => 'product.name', '$params' => ['attribute' => 'gone_name'], '$fallback' => null],
                'caption' => self::attributeBinding(''),
            ], children: [
                new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'heading', 1, fields: ['text' => self::attributeBinding('gone_size'), 'title' => self::attributeBinding('gone_fit')]),
            ]),
        ]);
        $content->setBlockTree(new StoredTree(new StoredLayout('standard', 1, ['hero' => ['title' => self::attributeBinding('gone_layout')]]), $content->getBlockTree()->blocks));
        $page = self::entry($content);
        // The layout's first, then the blocks', each once. An entry stores its tree as canonical JSON, so a block's fields
        // and a translatable field's locales are read in key order
        $this->productAttributes->findBy(['code' => ['gone_layout', 'material', 'gone_care', 'gone_fit', 'gone_size']])->shouldBeCalledOnce()->will(self::withCodes(ProductAttribute::class, ['material']));

        $unresolved = $this->unresolvedReferences()->in(self::export([$page]), [$page], []);

        self::assertSame([
            [self::PAGE, 'Autumn sale', 'product_attribute', 'gone_layout'],
            [self::PAGE, 'Autumn sale', 'product_attribute', 'gone_care'],
            [self::PAGE, 'Autumn sale', 'product_attribute', 'gone_fit'],
            [self::PAGE, 'Autumn sale', 'product_attribute', 'gone_size'],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_lists_what_the_editor_presets_it_writes_use_that_does_not_resolve(): void
    {
        $blockPreset = new ReadPreset(new Preset('Autumn hero', [
            self::snippetBlock(self::SNIPPET_GONE),
            self::snippetBlock(self::SNIPPET_IN_TARGET),
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1, fields: ['text' => self::attributeBinding('gone_fit')]),
        ], uuid: self::PRESET, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')), true);
        $startingPoint = new ReadPreset(new Preset(
            'Campaign page',
            [self::teaser(image: self::IMAGE_GONE), self::teaser(image: self::IMAGE_IN_EXPORT)],
            startingPoint: new StartingPoint('page', new StoredLayout('standard', 1, ['hero' => ['title' => self::attributeBinding('gone_layout')]])),
            uuid: self::STARTING_POINT,
            changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z'),
        ), true);

        $unresolved = $this->unresolvedReferences()->in(
            self::export([], [$blockPreset, $startingPoint], [self::mediaItem(self::IMAGE_IN_EXPORT, MediaKind::Image)]),
            [],
            [$blockPreset, $startingPoint],
        );

        self::assertSame([
            [self::PRESET, 'Autumn hero', 'entry', self::SNIPPET_GONE],
            [self::PRESET, 'Autumn hero', 'product_attribute', 'gone_fit'],
            [self::STARTING_POINT, 'Campaign page', 'media_item', self::IMAGE_GONE],
            [self::STARTING_POINT, 'Campaign page', 'product_attribute', 'gone_layout'],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_lists_the_locales_of_an_images_alt_text_the_target_lacks_but_not_another_kinds(): void
    {
        $image = self::mediaItem(self::IMAGE_IN_EXPORT, MediaKind::Image, ['xx_XX' => 'Eine Frau', 'en_US' => 'A woman', 'yy_YY' => '  ']);
        // What the check read from the file is a video, whatever its JSON says
        $video = self::mediaItem(self::IMAGE_IN_TARGET, MediaKind::Video, ['zz_ZZ' => 'Ein Video']);

        $unresolved = $this->unresolvedReferences()->in(self::export([], mediaItems: [$image, $video]), [], [], [$image, $video]);

        self::assertSame([[self::IMAGE_IN_EXPORT, 'Banner', 'locale', 'xx_XX']], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_lists_a_reference_once_for_each_record_that_holds_it_looking_each_type_up_once(): void
    {
        $settings = new EntrySettings(true, new DateWindow(), ['GONE', 'WEB'], Audience::everyone());
        $page = self::entry(self::page(self::PAGE, 'Autumn sale', [self::snippetBlock(self::SNIPPET_GONE), self::snippetBlock(self::SNIPPET_GONE)]), $settings);
        $other = self::entry(self::page(self::OTHER_PAGE, 'Spring sale', [self::snippetBlock(self::SNIPPET_GONE)]), $settings);
        $this->channels->findBy(['code' => ['GONE', 'WEB']])->shouldBeCalledOnce()->will(self::withCodes(Channel::class, ['WEB']));

        $unresolved = $this->unresolvedReferences()->in(self::export([$page, $other]), [$page, $other], []);

        self::assertSame([
            [self::PAGE, 'Autumn sale', 'entry', self::SNIPPET_GONE],
            [self::PAGE, 'Autumn sale', 'channel', 'GONE'],
            [self::OTHER_PAGE, 'Spring sale', 'entry', self::SNIPPET_GONE],
            [self::OTHER_PAGE, 'Spring sale', 'channel', 'GONE'],
        ], self::rows($unresolved));
    }

    /**
     * @test
     */
    public function it_looks_nothing_up_when_it_writes_nothing(): void
    {
        $this->channels->findBy(Argument::cetera())->shouldNotBeCalled();
        $this->locales->findBy(Argument::cetera())->shouldNotBeCalled();

        $page = self::entry(self::page(self::PAGE, 'Autumn sale', [self::snippetBlock(self::SNIPPET_GONE)]));

        self::assertSame([], $this->unresolvedReferences()->in(self::export([$page]), [], [], []));
    }

    private function unresolvedReferences(bool $withProducts = true): UnresolvedReferences
    {
        $contentTypes = self::contentTypes();
        $loaders = [
            // The target has these entries and media items, and the product jeans
            'entry' => $this->loader([self::SNIPPET_IN_TARGET]),
            'media_item' => $this->loader([self::IMAGE_IN_TARGET]),
        ];
        if ($withProducts) {
            $loaders['product'] = $this->loader(['jeans']);
        }

        return new UnresolvedReferences(
            new ReferenceFinder(
                new TreeValues(new BlockTypeRegistry(['teaser' => Teaser::class, 'snippet' => SnippetBlock::class], new FieldReader()), new LayoutRegistry([], new FieldReader())),
                new FieldHandlerLocator(new ServiceLocator([
                    EntryHandler::class => static fn (): EntryHandler => new EntryHandler($contentTypes),
                    MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
                    SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
                ])),
            ),
            Conditions::matcher(['product_available' => new ProductAvailable()]),
            new ServiceLocator(array_map(static fn (ReferenceTypeLoaderInterface $loader): \Closure => static fn (): ReferenceTypeLoaderInterface => $loader, $loaders)),
            $this->channels->reveal(),
            $this->customerGroups->reveal(),
            $this->locales->reveal(),
            $this->productAttributes->reveal(),
        );
    }

    /**
     * @param list<string> $existing the ids the target has
     */
    private function loader(array $existing): ReferenceTypeLoaderInterface
    {
        $loader = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $loader->existing(Argument::type('array'))->will(static fn (array $args): array => array_values(array_filter((array) $args[0], static fn (mixed $id): bool => in_array($id, $existing, true))));

        return $loader->reveal();
    }

    /**
     * A promise of the records with those of the codes asked for that the target has
     *
     * @param class-string<Channel|CustomerGroup|Locale|ProductAttribute> $class
     * @param list<string> $codes the codes the target has
     */
    private static function withCodes(string $class, array $codes): \Closure
    {
        return static function (array $args) use ($class, $codes): array {
            $criteria = $args[0];
            \assert(is_array($criteria) && is_array($criteria['code']));

            $records = [];
            foreach ($criteria['code'] as $code) {
                if (in_array($code, $codes, true)) {
                    $record = new $class();
                    $record->setCode($code);
                    $records[] = $record;
                }
            }

            return $records;
        };
    }

    /**
     * @param list<ReadEntry> $entries
     * @param list<ReadPreset> $presets
     * @param list<ReadMediaItem> $mediaItems
     */
    private static function export(array $entries, array $presets = [], array $mediaItems = []): ReadExport
    {
        $byUuid = static function (array $records): array {
            $keyed = [];
            foreach ($records as $record) {
                \assert($record instanceof ReadEntry || $record instanceof ReadPreset || $record instanceof ReadMediaItem);
                $keyed[$record->uuid()] = $record;
            }

            return $keyed;
        };

        /** @var array<string, ReadEntry> $entriesByUuid */
        $entriesByUuid = $byUuid($entries);
        /** @var array<string, ReadPreset> $presetsByUuid */
        $presetsByUuid = $byUuid($presets);
        /** @var array<string, ReadMediaItem> $mediaItemsByUuid */
        $mediaItemsByUuid = $byUuid($mediaItems);

        return new ReadExport(new Manifest('2.0.0', new \DateTimeImmutable('2026-11-13T15:00:00Z')), $entriesByUuid, $presetsByUuid, [], $mediaItemsByUuid);
    }

    /**
     * @param list<ExportedPlacement> $placements
     */
    private static function entry(EntryInterface $content, ?EntrySettings $settings = null, array $placements = [], bool $picked = true): ReadEntry
    {
        return new ReadEntry(
            new ExportedEntry(new \DateTimeImmutable('2026-11-12T10:15:00Z'), $settings ?? new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone()), $placements, []),
            $content,
            self::contentTypes()->getByEntry($content),
            $picked,
        );
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function page(string $uuid, string $title, array $blocks = []): Page
    {
        $page = new Page();
        $page->setUuid($uuid);
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setBlockTree(new StoredTree(null, $blocks));

        return $page;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function snippet(string $uuid, string $title, array $blocks = []): Snippet
    {
        $snippet = new Snippet();
        $snippet->setUuid($uuid);
        $snippet->setCurrentLocale('en_US');
        $snippet->setTitle($title);
        $snippet->setBlockTree(new StoredTree(null, $blocks));

        return $snippet;
    }

    /**
     * @param array<string, string> $alt
     */
    private static function mediaItem(string $uuid, MediaKind $kind, array $alt = []): ReadMediaItem
    {
        $changedAt = new \DateTimeImmutable('2026-11-11T12:00:00Z');

        return new ReadMediaItem(
            new ExportedMediaItem($uuid, null, MediaKind::Image, 'Banner', 'banner.jpg', 'image/jpeg', 100, 10, 10, null, $alt, null, null, str_repeat('a', 64), $changedAt),
            new CheckedFile($kind, MediaKind::Image === $kind ? 'image/webp' : 'video/mp4', MediaKind::Image === $kind ? 'webp' : 'mp4', '/nowhere/banner', 100, null, null, str_repeat('a', 64)),
            true,
            '/nowhere/banner',
        );
    }

    private static function snippetBlock(string $snippet): StoredBlock
    {
        return new StoredBlock(\Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'snippet', 1, fields: ['snippet' => $snippet]);
    }

    private static function teaser(?string $image = null, ?string $readMore = null): StoredBlock
    {
        return new StoredBlock(\Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'teaser', 1, fields: array_filter(['image' => $image, 'readMore' => $readMore], static fn (?string $value): bool => null !== $value));
    }

    /**
     * @return array<string, mixed>
     */
    private static function productCondition(string $product): array
    {
        return ['kind' => 'product_available', 'negate' => false, 'params' => ['product' => $product]];
    }

    /**
     * @return array<string, mixed> the Product attribute binding of the attribute
     */
    private static function attributeBinding(string $attribute): array
    {
        return ['$bind' => 'product.attribute', '$params' => ['attribute' => $attribute], '$fallback' => null];
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        ]);
    }

    /**
     * @param list<UnresolvedReference> $unresolved
     *
     * @return list<array{string, string, string, string}>
     */
    private static function rows(array $unresolved): array
    {
        return array_map(static fn (UnresolvedReference $reference): array => [$reference->uuid, $reference->label, $reference->type, $reference->id], $unresolved);
    }
}
