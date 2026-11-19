<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Banner;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Downloads;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Image;
use Setono\SyliusCMSPlugin\Preset\CodePresetExporter;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Hero;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;

/**
 * An editor preset exported to a code preset file: named by its name in snake case, without its UUID and changedAt,
 * with media fields left empty, because media items exist only in one environment's library, and written as canonical
 * JSON. See "Code presets" in docs/spec/layouts-and-presets.md and ADR 0012
 */
final class CodePresetExporterTest extends TestCase
{
    private const UUID = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const IMAGE = '0193a5be-0000-7000-8000-000000000001';

    private const HEADING = '0193a5be-0000-7000-8000-000000000002';

    private const MEDIA = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const OTHER_MEDIA = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f61';

    private const PROMO = <<<'JSON'
        {
            "format": 1,
            "name": "Promo",
            "category": "Campaigns",
            "description": null,
            "thumbnail": null,
            "startingPoint": null,
            "blocks": [
                {
                    "key": "0193a5be-0000-7000-8000-000000000002",
                    "type": "heading",
                    "version": 1,
                    "style": null,
                    "fields": {
                        "level": "h2",
                        "text": {
                            "da_DK": "Vinterudsalg",
                            "en_US": "Winter sale"
                        }
                    },
                    "translated": {},
                    "conditions": [],
                    "children": []
                },
                {
                    "key": "0193a5be-0000-7000-8000-000000000001",
                    "type": "image",
                    "version": 1,
                    "style": null,
                    "fields": {
                        "caption": {
                            "en_US": "Our shop"
                        }
                    },
                    "translated": {},
                    "conditions": [],
                    "children": []
                }
            ]
        }

        JSON;

    /**
     * @test
     */
    public function it_writes_the_preset_as_a_code_preset_file_in_canonical_json_with_its_media_fields_left_empty(): void
    {
        $exported = $this->exporter()->export(self::editorPreset('Promo', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['level' => 'h2', 'text' => ['en_US' => 'Winter sale', 'da_DK' => 'Vinterudsalg']]),
            new StoredBlock(self::IMAGE, 'image', 1, fields: ['image' => self::MEDIA, 'caption' => ['en_US' => 'Our shop']]),
        ], 'Campaigns'));

        self::assertSame('promo', $exported->code);
        self::assertSame(self::PROMO, $exported->contents);
        // It reads back as a code preset
        $read = (new PresetSerializer())->fromJson($exported->contents, $exported->code);
        self::assertTrue($read->isFromCode());
        self::assertSame('promo', $read->id());
    }

    /**
     * @test
     */
    public function it_empties_every_media_and_media_list_field_in_every_block_however_deep_and_keeps_the_rest(): void
    {
        $banner = new StoredBlock(self::IMAGE, 'banner', 1, fields: ['image' => self::MEDIA, 'mobileImage' => self::OTHER_MEDIA, 'heading' => ['en_US' => 'Sale'], 'fullWidth' => true]);
        $downloads = new StoredBlock(self::HEADING, 'downloads', 1, fields: ['documents' => [self::MEDIA, self::OTHER_MEDIA]]);
        $columns = new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'columns', 1, children: [
            new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'column', 1, children: [$banner, $downloads]),
        ]);

        $blocks = self::blocksOf($this->exporter()->export(self::editorPreset('Promo', [$columns]))->contents);

        $column = $blocks[0]['children'][0] ?? null;
        self::assertIsArray($column);
        self::assertIsArray($column['children'] ?? null);
        self::assertSame([['fullWidth' => true, 'heading' => ['en_US' => 'Sale']], []], array_column($column['children'], 'fields'));
        // The preset itself keeps its media references, as an editor preset in the store does
        self::assertSame(self::MEDIA, $banner->fields['image']);
    }

    /**
     * @test
     */
    public function it_empties_a_translatable_media_field_with_its_translation_state(): void
    {
        $gallery = new StoredBlock(self::IMAGE, 'gallery', 1, fields: ['poster' => ['en_US' => self::MEDIA], 'heading' => 'Shop'], translated: ['poster' => ['da_DK' => ['from' => 'en_US']]]);

        $blocks = self::blocksOf($this->exporter()->export(self::editorPreset('Promo', [$gallery]))->contents);

        self::assertSame(['heading' => 'Shop'], $blocks[0]['fields']);
        self::assertSame([], $blocks[0]['translated']);
    }

    /**
     * @test
     */
    public function it_leaves_a_block_of_a_type_the_code_no_longer_has_as_it_is(): void
    {
        $removed = new StoredBlock(self::IMAGE, 'removed_type', 1, fields: ['image' => self::MEDIA]);

        $blocks = self::blocksOf($this->exporter()->export(self::editorPreset('Promo', [$removed]))->contents);

        self::assertSame(['image' => self::MEDIA], $blocks[0]['fields']);
    }

    /**
     * @test
     */
    public function it_empties_the_media_fields_of_a_starting_point_s_layout_and_writes_that_it_is_not_the_default(): void
    {
        $preset = new Preset('Landing page', [], startingPoint: new StartingPoint('page', new StoredLayout('hero', 1, ['image' => self::MEDIA])), uuid: self::UUID, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z'));

        $read = (new PresetSerializer())->fromJson($this->exporter()->export($preset)->contents, 'landing_page');

        self::assertNotNull($read->startingPoint);
        self::assertSame('page', $read->startingPoint->contentType);
        self::assertSame([], $read->startingPoint->layout?->fields);
        self::assertFalse($read->startingPoint->default);
    }

    /**
     * @test
     */
    public function it_keeps_a_translation_key_and_names_the_file_by_it(): void
    {
        $preset = new Preset(new TranslationKey('app.preset.spring.name'), [], category: new TranslationKey('app.preset_category.campaigns'), uuid: self::UUID, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z'));

        $exported = $this->exporter()->export($preset);

        self::assertSame('app_preset_spring_name', $exported->code);
        self::assertEquals(new TranslationKey('app.preset_category.campaigns'), (new PresetSerializer())->fromJson($exported->contents, $exported->code)->category);
    }

    /**
     * @test
     *
     * @dataProvider names
     */
    public function it_names_the_file_by_the_preset_s_name_in_snake_case(string $name, string $code): void
    {
        self::assertSame($code, CodePresetExporter::codeOf($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'one word' => ['Promo', 'promo'];
        yield 'words' => ['Autumn hero', 'autumn_hero'];
        yield 'camel case' => ['SpringSale', 'spring_sale'];
        yield 'punctuation' => ['Campaign – 2026!', 'campaign_2026'];
        yield 'accents' => ['Øko-tilbud på æbler', 'oko_tilbud_pa_aebler'];
        yield 'a digit first' => ['2026 sale', 'preset_2026_sale'];
        yield 'nothing left' => ['★★★', 'preset'];
    }

    private function exporter(): CodePresetExporter
    {
        return new CodePresetExporter(
            new BlockTypeRegistry([
                'heading' => Heading::class,
                'image' => Image::class,
                'banner' => Banner::class,
                'downloads' => Downloads::class,
                'columns' => Columns::class,
                'column' => Column::class,
                'gallery' => Gallery::class,
            ], new FieldReader()),
            new LayoutRegistry(['page' => ['hero' => Hero::class]], new FieldReader()),
        );
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function editorPreset(string $name, array $blocks, ?string $category = null): Preset
    {
        return new Preset($name, $blocks, $category, uuid: self::UUID, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z'));
    }

    /**
     * @return list<array{fields: array<string, mixed>, translated: array<string, mixed>, children: list<array<string, mixed>>}>
     */
    private static function blocksOf(string $contents): array
    {
        /** @var array{blocks: list<array{fields: array<string, mixed>, translated: array<string, mixed>, children: list<array<string, mixed>>}>} $file */
        $file = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return $file['blocks'];
    }
}
