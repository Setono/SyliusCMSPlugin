<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Problem\Kind\PresetWithRemovedBlockTypes;
use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\PresetProblemFinder;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset with blocks of removed types is a warning problem, which the Overview's Needs attention lists and the Content
 * badge counts. Presets aren't entries, so their problems are found apart from the entries'. See "Problems" in
 * docs/spec/admin.md and "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class PresetProblemFinderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_a_warning_for_each_preset_with_blocks_of_types_the_code_no_longer_has_naming_them(): void
    {
        $retired = new Preset('Retired', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'gallery', 1, children: [new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'slideshow', 1)]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'heading', 1, children: [new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'quote', 1)]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'gallery', 1),
        ], code: 'retired');
        $hero = new Preset('Hero', [new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'heading', 1)], code: 'hero');
        $presets = $this->prophesize(PresetProviderInterface::class);
        $presets->all()->willReturn([$retired, $hero]);

        $finder = new PresetProblemFinder($presets->reveal(), [new PresetWithRemovedBlockTypes(new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()))]);

        $expected = [new PresetProblem(PresetWithRemovedBlockTypes::CODE, Severity::Warning, $retired, new TranslatableMessage('setono_sylius_cms.problem.preset_removed_block_type', ['%types%' => '"gallery", "quote"']))];
        self::assertEquals($expected, $finder->findAll());
        self::assertEquals($expected, $finder->findIn($retired));
        self::assertSame([], $finder->findIn($hero));
        self::assertEquals(new ProblemCount(0, 1), $finder->count());
    }
}
