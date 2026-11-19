<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Upgrade\PresetRewriter;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;

/**
 * setono:cms:upgrade writes editor presets back upgraded: a block preset's blocks, and a starting point's layout and
 * blocks as a tree of its content type, keeping its changedAt. See "Console commands" in docs/spec/extending.md
 */
final class PresetRewriterTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
    }

    /**
     * @test
     */
    public function it_upgrades_a_starting_point_s_layout_and_blocks_as_a_tree_of_its_content_type_keeping_its_changed_at(): void
    {
        $row = self::row(new Preset('Landing page', [new StoredBlock(self::KEY, 'callout', 1, fields: ['text' => 'Hello'])], startingPoint: new StartingPoint('page', new StoredLayout('hero', 1, ['tagline' => 'Hi']))));
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static function (array $arguments): Upgraded {
            /** @var array{layout: array<string, mixed>, blocks: list<array<string, mixed>>} $tree */
            $tree = $arguments[0];
            $tree['layout']['version'] = 2;
            $tree['blocks'][0]['version'] = 2;

            return new Upgraded($tree);
        });

        $rewrite = (new PresetRewriter(new PresetUpgrader($this->upgrader->reveal())))->rewrite($row);

        self::assertTrue($rewrite->rewritten);
        self::assertSame([], $rewrite->newer);
        $upgraded = (new EditorPresetContent())->read($row);
        self::assertSame([2, 2], [$upgraded->startingPoint?->layout?->version, $upgraded->blocks[0]->version]);
        self::assertSame('page', $upgraded->startingPoint?->contentType);
        self::assertEquals(new \DateTimeImmutable('2026-11-10T08:00:00Z'), $row->getChangedAt());
    }

    /**
     * @test
     */
    public function it_says_it_rewrote_nothing_when_the_preset_is_up_to_date(): void
    {
        $row = self::row(new Preset('Callout', [new StoredBlock(self::KEY, 'callout', 2)]));
        $content = $row->getContent();
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));

        self::assertFalse((new PresetRewriter(new PresetUpgrader($this->upgrader->reveal())))->rewrite($row)->rewritten);
        self::assertSame($content, $row->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_a_preset_it_can_not_read_as_it_is(): void
    {
        $row = new EditorPreset();
        $row->setContent('{"format": 1}');

        $rewrite = (new PresetRewriter(new PresetUpgrader($this->upgrader->reveal())))->rewrite($row);

        self::assertFalse($rewrite->rewritten);
        self::assertSame('{"format": 1}', $row->getContent());
    }

    private static function row(Preset $preset): EditorPreset
    {
        $row = new EditorPreset();
        (new EditorPresetContent())->write($row, new Preset($preset->name, $preset->blocks, startingPoint: $preset->startingPoint, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')));

        return $row;
    }
}
