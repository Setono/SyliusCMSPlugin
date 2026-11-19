<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Preset\CodePresetFiles;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * The code presets the plugin ships, in its presets directory: page starting points and block presets, whose text is
 * all translation keys translated in the plugin's own translation files, and whose images are empty. See "Shipped code
 * presets" and "Code presets" in docs/spec/layouts-and-presets.md
 */
final class ShippedPresetsTest extends TestCase
{
    private const RESOURCES = __DIR__ . '/../../../src/Resources';

    private const DIRECTORY = self::RESOURCES . '/presets';

    /** Where the public paths of the plugin's assets, /bundles/setonosyliuscmsplugin/…, are in the plugin */
    private const PUBLIC_PATH = '/bundles/setonosyliuscmsplugin/';

    /** A locale code, which a value the editor typed is kept by in a locale map */
    private const LOCALE_CODE = '/^[a-z]{2}(?:_[A-Z]{2})?$/';

    /** @var array<string, MessageCatalogue> by domain */
    private static array $catalogues = [];

    /**
     * @test
     */
    public function it_ships_the_starting_points_and_block_presets_the_spec_lists(): void
    {
        $codes = array_keys(CodePresetFiles::inDirectory(self::DIRECTORY));

        self::assertSame([
            'banner_carousel',
            'bestsellers_carousel',
            'campaign_with_fallback',
            'content_page',
            'faq',
            'footer_links',
            'hero',
            'image_and_text',
            'landing_page',
            'related_products',
            'three_usps',
        ], $codes);
    }

    /**
     * @test
     *
     * @dataProvider provideShippedPresets
     */
    public function each_holds_what_the_spec_lists(string $code, ?StartingPoint $startingPoint, string $category, string $blocks): void
    {
        $preset = self::preset($code);

        self::assertEquals(new TranslationKey(sprintf('setono_sylius_cms.preset.%s.name', $code)), $preset->name);
        self::assertEquals(new TranslationKey(sprintf('setono_sylius_cms.preset_category.%s', $category)), $preset->category);
        self::assertEquals(new TranslationKey(sprintf('setono_sylius_cms.preset.%s.description', $code)), $preset->description);
        self::assertSame($blocks, self::outline($preset->blocks));
        self::assertSame(null === $startingPoint, null === $preset->startingPoint);
        if (null !== $startingPoint) {
            self::assertSame($startingPoint->contentType, $preset->startingPoint?->contentType);
            self::assertSame($startingPoint->layout?->code, $preset->startingPoint?->layout?->code);
            self::assertSame($startingPoint->layout?->fields, $preset->startingPoint?->layout?->fields);
            // No starting point for Page is the default, so the create dialog preselects Blank
            self::assertFalse($preset->startingPoint?->default);
        }
    }

    /**
     * @return iterable<string, array{string, StartingPoint|null, string, string}>
     */
    public function provideShippedPresets(): iterable
    {
        $usp = 'column(image, heading, rich_text)';
        $bannerCarousel = 'carousel(banner(button), banner(button), banner(button))';

        yield 'landing_page' => ['landing_page', new StartingPoint('page', new StoredLayout('standard', 1, ['showTitle' => false])), 'pages', sprintf('%1$s, products, columns(%2$s, %2$s, %2$s), rich_text', $bannerCarousel, $usp)];
        yield 'content_page' => ['content_page', new StartingPoint('page', new StoredLayout('standard', 1)), 'pages', 'heading, rich_text'];
        yield 'banner_carousel' => ['banner_carousel', null, 'banners', $bannerCarousel];
        yield 'hero' => ['hero', null, 'banners', 'banner(button, button)'];
        yield 'campaign_with_fallback' => ['campaign_with_fallback', null, 'banners', 'first_match(banner(button), banner(button))'];
        yield 'image_and_text' => ['image_and_text', null, 'content', 'columns(column(image), column(heading, rich_text, button))'];
        yield 'three_usps' => ['three_usps', null, 'content', sprintf('columns(%1$s, %1$s, %1$s)', $usp)];
        yield 'faq' => ['faq', null, 'content', 'accordion(accordion_item, accordion_item, accordion_item)'];
        yield 'bestsellers_carousel' => ['bestsellers_carousel', null, 'products', 'products'];
        yield 'related_products' => ['related_products', null, 'products', 'products'];
        yield 'footer_links' => ['footer_links', null, 'navigation', 'columns(column(link_list(link, link, link)), column(link_list(link, link, link)), column(link_list(link, link, link)))'];
    }

    /**
     * @test
     */
    public function its_hero_is_a_full_width_banner_that_loads_first_with_a_primary_and_a_secondary_button(): void
    {
        $banner = self::preset('hero')->blocks[0];

        self::assertTrue($banner->fields['fullWidth'] ?? null);
        self::assertTrue($banner->fields['priority'] ?? null);
        self::assertSame(['primary', 'secondary'], array_map(static fn (StoredBlock $button): ?string => $button->style, $banner->children));
    }

    /**
     * @test
     */
    public function its_campaign_with_fallback_shows_a_banner_in_a_date_window_and_otherwise_a_default_banner(): void
    {
        [$campaign, $fallback] = self::preset('campaign_with_fallback')->blocks[0]->children;

        // The window is left open for the editor to set, as any date in a shipped file would be in the past one day
        self::assertEquals([['kind' => 'date_window', 'negate' => false, 'params' => new \stdClass()]], $campaign->conditions);
        self::assertSame([], $fallback->conditions);
    }

    /**
     * @test
     */
    public function its_products_presets_are_filled_by_the_bestsellers_in_a_carousel_and_by_every_type_of_product_association(): void
    {
        $bestsellers = self::preset('bestsellers_carousel')->blocks[0];
        $related = self::preset('related_products')->blocks[0];

        self::assertEquals(['display' => 'carousel', 'products' => ['$source' => 'bestsellers', '$params' => [], '$limit' => 8]], $bestsellers->fields);
        self::assertEquals(['products' => ['$source' => 'product_associations', '$params' => [], '$limit' => 8]], $related->fields);
    }

    /**
     * @test
     *
     * @dataProvider provideFiles
     */
    public function every_file_parses_and_is_written_in_canonical_json(string $code, string $path): void
    {
        $json = (string) file_get_contents($path);

        self::assertSame($json, (new PresetSerializer())->toJson((new PresetSerializer())->fromJson($json, $code)));
    }

    /**
     * @test
     *
     * @dataProvider provideFiles
     */
    public function every_translation_key_in_a_file_has_an_english_translation(string $code, string $path): void
    {
        $keys = self::translationKeysIn(json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR));

        self::assertNotSame([], $keys);
        foreach ($keys as $key) {
            $catalogue = self::englishCatalogue($key->domain);
            self::assertTrue($catalogue->defines($key->key, $key->domain), sprintf('%s has no English translation in the domain %s', $key->key, $key->domain));
            self::assertNotSame('', trim($catalogue->get($key->key, $key->domain)), sprintf('%s is translated as nothing', $key->key));
        }
    }

    /**
     * @test
     *
     * @dataProvider provideFiles
     */
    public function all_the_text_in_a_file_is_translation_keys(string $code, string $path): void
    {
        $preset = self::preset($code);

        self::assertInstanceOf(TranslationKey::class, $preset->name);
        self::assertInstanceOf(TranslationKey::class, $preset->category);
        self::assertInstanceOf(TranslationKey::class, $preset->description);
        foreach (self::blocksIn($preset->blocks) as $block) {
            self::assertSame([], self::localeMapsIn($block->fields), sprintf('A value of the %s block %s is kept by locale', $block->type, $block->key));
            self::assertSame([], $block->translated);
        }

        self::assertSame([], self::localeMapsIn($preset->startingPoint?->layout?->fields ?? []));
    }

    /**
     * @test
     *
     * @dataProvider provideFiles
     */
    public function the_images_in_a_file_are_empty(string $code, string $path): void
    {
        $blockTypes = self::blockTypes();

        $media = [];
        foreach (self::blocksIn(self::preset($code)->blocks) as $block) {
            foreach ($blockTypes->get($block->type)->fields as $name => $field) {
                if (($field->field instanceof Media || $field->field instanceof MediaList) && array_key_exists($name, $block->fields)) {
                    $media[] = sprintf('%s in the %s block %s', $name, $block->type, $block->key);
                }
            }
        }

        self::assertSame([], $media);
    }

    /**
     * @test
     *
     * @dataProvider provideFiles
     */
    public function the_starting_points_have_a_thumbnail_the_plugin_ships_and_the_block_presets_none(string $code, string $path): void
    {
        $preset = self::preset($code);

        if (null === $preset->startingPoint) {
            self::assertNull($preset->thumbnail);

            return;
        }

        self::assertSame(sprintf('%sthumbnail/preset/%s.svg', self::PUBLIC_PATH, $code), $preset->thumbnail);
        self::assertFileExists(sprintf('%s/public/%s', self::RESOURCES, substr($preset->thumbnail, strlen(self::PUBLIC_PATH))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideFiles(): iterable
    {
        foreach (CodePresetFiles::inDirectory(self::DIRECTORY) as $code => $path) {
            yield $code => [$code, $path];
        }
    }

    private static function preset(string $code): Preset
    {
        return (new PresetSerializer())->fromJson((string) file_get_contents(sprintf('%s/%s.json', self::DIRECTORY, $code)), $code);
    }

    /**
     * The blocks' types with their children's in brackets, such as "columns(column(image), column(heading))"
     *
     * @param list<StoredBlock> $blocks
     */
    private static function outline(array $blocks): string
    {
        return implode(', ', array_map(static fn (StoredBlock $block): string => [] === $block->children ? $block->type : sprintf('%s(%s)', $block->type, self::outline($block->children)), $blocks));
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<StoredBlock> the blocks and every block inside them
     */
    private static function blocksIn(array $blocks): array
    {
        $all = [];
        foreach ($blocks as $block) {
            $all = [...$all, $block, ...self::blocksIn($block->children)];
        }

        return $all;
    }

    /**
     * @return list<TranslationKey> every translation key in a decoded file, wherever it sits
     */
    private static function translationKeysIn(mixed $value): array
    {
        $key = TranslationKey::fromValue($value);
        if (null !== $key) {
            return [$key];
        }

        if (!is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $inner) {
            $keys = [...$keys, ...self::translationKeysIn($inner)];
        }

        return $keys;
    }

    /**
     * @return list<array<array-key, mixed>> the maps in a value that hold something by locale code, wherever they sit
     */
    private static function localeMapsIn(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $maps = [];
        foreach ($value as $name => $inner) {
            if (is_string($name) && 1 === preg_match(self::LOCALE_CODE, $name)) {
                return [$value];
            }

            $maps = [...$maps, ...self::localeMapsIn($inner)];
        }

        return $maps;
    }

    private static function englishCatalogue(string $domain): MessageCatalogue
    {
        return self::$catalogues[$domain] ??= (new YamlFileLoader())->load(sprintf('%s/translations/%s.en.yaml', self::RESOURCES, $domain), 'en', $domain);
    }

    /**
     * The shipped block types, by the codes their #[BlockType] gives them
     */
    private static function blockTypes(): BlockTypeRegistry
    {
        $classes = [];
        foreach (glob(self::RESOURCES . '/../Block/*.php') ?: [] as $file) {
            $class = 'Setono\\SyliusCMSPlugin\\Block\\' . basename($file, '.php');
            $attribute = class_exists($class) ? ((new \ReflectionClass($class))->getAttributes(BlockType::class)[0] ?? null) : null;
            if (null !== $attribute) {
                $classes[$attribute->newInstance()->code] = $class;
            }
        }

        return new BlockTypeRegistry($classes, new FieldReader());
    }
}
