<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Preset\CodePresetProvider;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;

/**
 * The code presets, read from the files in the registered directories, without those setono_sylius_cms.disabled.presets
 * switches off. See "Code presets" in docs/spec/layouts-and-presets.md
 */
final class CodePresetProviderTest extends TestCase
{
    use ProphecyTrait;
    use TemporaryDirectory;

    /**
     * @test
     */
    public function it_lists_the_code_presets_read_from_their_files_leaving_out_the_disabled_ones(): void
    {
        $provider = new CodePresetProvider([
            'hero' => $this->presetFile('hero', 'Hero'),
            'faq' => $this->presetFile('faq', 'FAQ'),
            'promo' => $this->presetFile('promo', 'Promo'),
        ], ['faq'], new NullLogger());

        $presets = $provider->all();

        self::assertSame(['hero', 'promo'], array_map(static fn (Preset $preset): string => $preset->id(), $presets));
        self::assertSame('Hero', $presets[0]->name);
        self::assertTrue($presets[0]->isFromCode());
    }

    /**
     * @test
     */
    public function it_finds_a_code_preset_by_its_code_but_not_a_disabled_or_unknown_one(): void
    {
        $provider = new CodePresetProvider(['hero' => $this->presetFile('hero', 'Hero'), 'faq' => $this->presetFile('faq', 'FAQ')], ['faq'], new NullLogger());

        self::assertSame('Hero', $provider->find('hero')?->name);
        self::assertNull($provider->find('faq'));
        self::assertNull($provider->find('banner'));
        self::assertNull($provider->find('0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b'));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_file_that_is_not_in_the_format_and_logs_why(): void
    {
        $broken = $this->temporaryFile('broken.json', '{"format": 2}');
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('can\'t be read'), Argument::withEntry('file', $broken))->shouldBeCalledOnce();

        $provider = new CodePresetProvider(['broken' => $broken, 'hero' => $this->presetFile('hero', 'Hero')], [], $logger->reveal());

        self::assertSame(['hero'], array_map(static fn (Preset $preset): string => $preset->id(), $provider->all()));
        self::assertNull($provider->find('broken'));
    }

    private function presetFile(string $code, string $name): string
    {
        return $this->temporaryFile($code . '.json', json_encode([
            'format' => 1,
            'name' => $name,
            'category' => null,
            'description' => null,
            'thumbnail' => null,
            'startingPoint' => null,
            'blocks' => [],
        ], \JSON_THROW_ON_ERROR));
    }
}
