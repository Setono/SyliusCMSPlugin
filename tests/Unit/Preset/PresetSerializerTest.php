<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;

/**
 * The preset file format, which code presets, editor presets and exports use. See "Preset file format" in
 * docs/spec/layouts-and-presets.md
 */
final class PresetSerializerTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const UUID = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    /** The spec's example of a code preset, as a file */
    private const HERO = <<<'JSON'
        {
            "format": 1,
            "name": {
                "$trans": "setono_sylius_cms.preset.hero.name"
            },
            "category": {
                "$trans": "setono_sylius_cms.preset_category.banners"
            },
            "description": {
                "$trans": "setono_sylius_cms.preset.hero.description"
            },
            "thumbnail": null,
            "startingPoint": null,
            "blocks": [
                {
                    "key": "0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f",
                    "type": "banner",
                    "version": 1,
                    "style": null,
                    "fields": {
                        "fullWidth": true,
                        "heading": {
                            "$trans": "setono_sylius_cms.preset.hero.heading"
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
    public function it_writes_a_code_preset_as_a_pretty_printed_file_in_canonical_json(): void
    {
        $banner = new StoredBlock(self::KEY, 'banner', 1, fields: [
            'heading' => ['$trans' => 'setono_sylius_cms.preset.hero.heading'],
            'fullWidth' => true,
        ]);
        $preset = new Preset(
            new TranslationKey('setono_sylius_cms.preset.hero.name'),
            [$banner],
            category: new TranslationKey('setono_sylius_cms.preset_category.banners'),
            description: new TranslationKey('setono_sylius_cms.preset.hero.description'),
            code: 'hero',
        );

        self::assertSame(self::HERO, (new PresetSerializer())->toJson($preset));
    }

    /**
     * @test
     */
    public function it_reads_a_code_preset_whose_code_is_given_by_its_file(): void
    {
        $preset = (new PresetSerializer())->fromJson(self::HERO, 'hero');

        self::assertSame('hero', $preset->code);
        self::assertTrue($preset->isFromCode());
        self::assertSame('hero', $preset->id());
        self::assertEquals(new TranslationKey('setono_sylius_cms.preset.hero.name'), $preset->name);
        self::assertEquals(new TranslationKey('setono_sylius_cms.preset_category.banners'), $preset->category);
        self::assertNull($preset->thumbnail);
        self::assertNull($preset->startingPoint);
        self::assertCount(1, $preset->blocks);
        self::assertSame('banner', $preset->blocks[0]->type);
        self::assertSame(['$trans' => 'setono_sylius_cms.preset.hero.heading'], $preset->blocks[0]->fields['heading'] ?? null);
    }

    /**
     * @test
     */
    public function it_writes_back_the_file_it_reads(): void
    {
        $serializer = new PresetSerializer();

        self::assertSame(self::HERO, $serializer->toJson($serializer->fromJson(self::HERO, 'hero')));
    }

    /**
     * @test
     */
    public function it_writes_plain_text_and_a_translation_key_of_another_domain(): void
    {
        $json = (new PresetSerializer())->toJson(new Preset('Hero', [], category: new TranslationKey('app.preset_category.banners', 'app'), thumbnail: '/images/hero.png', code: 'hero'));

        self::assertStringContainsString('"name": "Hero",', $json);
        self::assertStringContainsString("\"category\": {\n        \"\$domain\": \"app\",\n        \"\$trans\": \"app.preset_category.banners\"\n    },", $json);
        self::assertStringContainsString('"description": null,', $json);
        self::assertStringContainsString('"thumbnail": "/images/hero.png",', $json);

        $read = (new PresetSerializer())->fromJson($json, 'hero');
        self::assertSame('Hero', $read->name);
        self::assertEquals(new TranslationKey('app.preset_category.banners', 'app'), $read->category);
        self::assertSame('/images/hero.png', $read->thumbnail);
    }

    /**
     * @test
     */
    public function it_writes_an_editor_preset_with_its_uuid_and_changed_at_right_after_the_format_and_no_thumbnail(): void
    {
        $preset = new Preset('Promo', [], category: 'Campaigns', uuid: self::UUID, changedAt: new \DateTimeImmutable('2026-11-13 10:30:00', new \DateTimeZone('Europe/Copenhagen')));

        $json = (new PresetSerializer())->toJson($preset);

        self::assertSame(<<<'JSON'
            {
                "format": 1,
                "uuid": "0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b",
                "changedAt": "2026-11-13T09:30:00Z",
                "name": "Promo",
                "category": "Campaigns",
                "description": null,
                "startingPoint": null,
                "blocks": []
            }

            JSON, $json);

        $read = (new PresetSerializer())->fromJson($json);
        self::assertFalse($read->isFromCode());
        self::assertSame(self::UUID, $read->id());
        self::assertEquals(new \DateTimeImmutable('2026-11-13T09:30:00Z'), $read->changedAt);
        self::assertNull($read->thumbnail);
    }

    /**
     * @test
     */
    public function it_writes_a_starting_point_with_its_layout_as_the_tree_stores_it_and_default_only_in_a_code_preset(): void
    {
        $startingPoint = new StartingPoint('page', new StoredLayout('standard', 1, ['showTitle' => false]), true);
        $serializer = new PresetSerializer();

        $code = $serializer->toJson(new Preset('Landing page', [], startingPoint: $startingPoint, code: 'landing_page'));
        $editor = $serializer->toJson(new Preset('Landing page', [], startingPoint: $startingPoint, uuid: self::UUID, changedAt: new \DateTimeImmutable('2026-11-13T09:30:00Z')));

        self::assertStringContainsString(<<<'JSON'
                "startingPoint": {
                    "contentType": "page",
                    "layout": {
                        "code": "standard",
                        "version": 1,
                        "fields": {
                            "showTitle": false
                        },
                        "translated": {}
                    },
                    "default": true
                },
            JSON, $code);
        self::assertStringNotContainsString('"default"', $editor);

        $read = $serializer->fromJson($code, 'landing_page')->startingPoint;
        self::assertNotNull($read);
        self::assertSame('page', $read->contentType);
        self::assertTrue($read->default);
        self::assertSame(['showTitle' => false], $read->layout?->fields);
        self::assertFalse($serializer->fromJson($editor)->startingPoint?->default);
    }

    /**
     * @test
     */
    public function it_reads_a_starting_point_of_a_content_type_without_layouts(): void
    {
        $json = str_replace('"startingPoint": null', '"startingPoint": {"contentType": "faq_item", "layout": null, "default": false}', self::HERO);

        $startingPoint = (new PresetSerializer())->fromJson($json, 'hero')->startingPoint;

        self::assertNotNull($startingPoint);
        self::assertSame('faq_item', $startingPoint->contentType);
        self::assertNull($startingPoint->layout);
    }

    /**
     * @test
     *
     * @dataProvider invalidFiles
     */
    public function it_refuses_a_file_that_is_not_in_the_format(string $json, string $message): void
    {
        $this->expectException(InvalidPresetException::class);
        $this->expectExceptionMessage($message);

        (new PresetSerializer())->fromJson($json, 'hero');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'not JSON' => ['{', 'The preset is not valid JSON'];
        yield 'not an object' => ['[]', 'The preset must be a JSON object'];
        yield 'another format' => [str_replace('"format": 1', '"format": 2', self::HERO), 'The preset must have the format 1'];
        yield 'no name' => [str_replace('"name": {' . "\n" . '        "$trans": "setono_sylius_cms.preset.hero.name"' . "\n" . '    },', '', self::HERO), 'The preset must have a name'];
        yield 'an empty name' => [self::with('"name"', '""'), 'The preset must have a name'];
        yield 'a name that is a number' => [self::with('"name"', '1'), 'The name of the preset must be a string or a translation key'];
        yield 'a translation key with more' => [self::with('"name"', '{"$trans": "a", "other": 1}'), 'The name of the preset must be a string or a translation key'];
        yield 'a category that is a list' => [self::with('"category"', '[]'), 'The category of the preset must be a string, a translation key or null'];
        yield 'a code preset without a thumbnail' => [str_replace('"thumbnail": null,', '', self::HERO), 'A code preset must have a thumbnail, or null'];
        yield 'a thumbnail that is a number' => [str_replace('"thumbnail": null', '"thumbnail": 1', self::HERO), 'The thumbnail of the preset must be a string or null'];
        yield 'an unknown key' => [str_replace('"thumbnail": null,', '"thumbnail": null, "colour": "red",', self::HERO), 'The preset has a key the format doesn\'t know: "colour"'];
        yield 'a starting point without a content type' => [str_replace('"startingPoint": null', '"startingPoint": {"layout": null, "default": false}', self::HERO), 'The starting point must have a content type code'];
        yield 'a starting point whose default is not a toggle' => [str_replace('"startingPoint": null', '"startingPoint": {"contentType": "page", "layout": null, "default": "yes"}', self::HERO), 'The starting point\'s default must be true or false'];
        yield 'blocks that are not a list' => [(string) preg_replace('/"blocks": \[.*\]/s', '"blocks": {}', self::HERO), 'The preset must have a list of blocks'];
        yield 'an invalid block' => [str_replace('"version": 1,', '"version": 0,', self::HERO), 'The block "0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f" must have a version of 1 or more'];
        yield 'an editor preset without changedAt' => [str_replace('"format": 1,', '"format": 1, "uuid": "' . self::UUID . '",', str_replace('"thumbnail": null,', '', self::HERO)), 'An editor preset must have a UUID and changedAt'];
        yield 'an editor preset with a thumbnail' => [str_replace('"format": 1,', '"format": 1, "uuid": "' . self::UUID . '", "changedAt": "2026-11-13T09:30:00Z",', self::HERO), 'The preset has a key the format doesn\'t know: "thumbnail"'];
        yield 'an editor preset with a bad changedAt' => [str_replace('"format": 1,', '"format": 1, "uuid": "' . self::UUID . '", "changedAt": "yesterday",', str_replace('"thumbnail": null,', '', self::HERO)), 'The changedAt of the preset must be a UTC date and time, such as 2026-11-13T09:30:00Z'];
    }

    private static function with(string $key, string $value): string
    {
        return (string) preg_replace('/' . preg_quote($key, '/') . ': (\{[^}]*\}|null)/', $key . ': ' . $value, self::HERO, 1);
    }
}
