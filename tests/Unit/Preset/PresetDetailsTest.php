<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetDetailsException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetDetails;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A preset's name, category and description, as editors give and change them. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
final class PresetDetailsTest extends TestCase
{
    /**
     * @test
     */
    public function it_takes_the_name_category_and_description_trimmed_with_an_empty_one_as_none(): void
    {
        $details = (new PresetDetails(''))->changedBy(['name' => ' Promo ', 'category' => "Campaigns\n", 'description' => '  ']);

        self::assertSame(['Promo', 'Campaigns', null], [$details->name, $details->category, $details->description]);
    }

    /**
     * @test
     */
    public function it_changes_only_what_the_input_has(): void
    {
        $details = new PresetDetails('Promo', 'Campaigns', 'For the spring sale');

        self::assertEquals(new PresetDetails('Spring promo', 'Campaigns', 'For the spring sale'), $details->changedBy(['name' => 'Spring promo']));
        self::assertEquals(new PresetDetails('Promo', null, 'For the spring sale'), $details->changedBy(['category' => null]));
        self::assertEquals($details, $details->changedBy([]));
    }

    /**
     * @test
     */
    public function it_keeps_a_translation_key_the_input_does_not_change(): void
    {
        $details = new PresetDetails(new TranslationKey('app.preset.hero.name'), new TranslationKey('app.preset_category.banners'));

        self::assertEquals(new PresetDetails(new TranslationKey('app.preset.hero.name'), 'Campaigns'), $details->changedBy(['category' => 'Campaigns']));
    }

    /**
     * @test
     *
     * @dataProvider invalidInputs
     *
     * @param array<string, mixed> $input
     */
    public function it_refuses_no_name_or_a_value_that_is_not_text(array $input, string $message): void
    {
        try {
            (new PresetDetails('Promo'))->changedBy($input);
            self::fail('The input was taken');
        } catch (InvalidPresetDetailsException $e) {
            self::assertSame($message, $e->translatable->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'an empty name' => [['name' => '  '], 'setono_sylius_cms.preset.name_required'];
        yield 'no name' => [['name' => null], 'setono_sylius_cms.preset.name_required'];
        yield 'a list' => [['category' => ['Campaigns']], 'setono_sylius_cms.preset.details_not_text'];
        yield 'a number' => [['description' => 42], 'setono_sylius_cms.preset.details_not_text'];
    }

    /**
     * @test
     */
    public function it_applies_to_a_preset_leaving_the_rest_as_it_is(): void
    {
        $changedAt = new \DateTimeImmutable('2026-11-10T08:00:00Z');
        $blocks = [new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1)];
        $preset = new Preset('Promo', $blocks, 'Campaigns', startingPoint: new StartingPoint('page', null), uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a', changedAt: $changedAt);

        $renamed = (new PresetDetails('Spring promo', null, 'For the spring sale'))->applyTo($preset);

        self::assertEquals(new Preset('Spring promo', $blocks, null, 'For the spring sale', new StartingPoint('page', null), uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a', changedAt: $changedAt), $renamed);
    }
}
