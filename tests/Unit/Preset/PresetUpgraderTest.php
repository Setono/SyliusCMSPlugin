<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset brought up to date with the code as it's read, which inserting, creating from a starting point, validating,
 * setono:cms:upgrade and an import all act on. See "Schema changes" in docs/spec/content-model.md
 */
final class PresetUpgraderTest extends TestCase
{
    use ProphecyTrait;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
    }

    /**
     * @test
     */
    public function it_upgrades_a_block_presets_blocks_in_the_stored_format(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static function (array $arguments): Upgraded {
            /** @var list<array{version: int, fields: array<string, mixed>}> $blocks */
            $blocks = $arguments[0];
            $blocks[0]['version'] = 2;
            $blocks[0]['fields'] = ['title' => $blocks[0]['fields']['text'] ?? null];

            return new Upgraded($blocks);
        });

        $upgraded = (new PresetUpgrader($this->upgrader->reveal()))->upgrade(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 1, null, ['text' => ['en_US' => 'Hi']])], code: 'hero'));

        self::assertFalse($upgraded->hasNewer());
        self::assertEquals([new StoredBlock(self::HEADING, 'heading', 2, null, ['title' => ['en_US' => 'Hi']])], $upgraded->tree()->blocks);
        self::assertNull($upgraded->tree()->layout);
    }

    /**
     * @test
     */
    public function it_upgrades_a_starting_point_as_a_tree_of_its_content_type(): void
    {
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static function (array $arguments): Upgraded {
            /** @var array{layout: array<string, mixed>, blocks: list<array<string, mixed>>} $tree */
            $tree = $arguments[0];
            $tree['layout']['version'] = 3;
            $tree['blocks'][0]['version'] = 2;

            return new Upgraded($tree);
        });

        $upgraded = (new PresetUpgrader($this->upgrader->reveal()))->upgrade(new Preset(
            'Campaign page',
            [new StoredBlock(self::HEADING, 'heading', 1)],
            startingPoint: new StartingPoint('page', new StoredLayout('campaign', 2, ['dark' => true])),
            code: 'campaign_page',
        ));

        self::assertEquals(new StoredLayout('campaign', 3, ['dark' => true]), $upgraded->tree()->layout);
        self::assertSame(2, $upgraded->tree()->blocks[0]->version);
    }

    /**
     * Inserting and creating refuse it, and validation reports it, each with one message per block or layout
     *
     * @test
     */
    public function it_lists_what_was_saved_with_a_newer_version_than_the_codes_with_a_message_for_each(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [
            new NewerVersion(Kind::BlockType, 'heading', 3, 1),
            new NewerVersion(Kind::BlockType, 'button', 2, 1),
        ]));

        $upgraded = (new PresetUpgrader($this->upgrader->reveal()))->upgrade(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 3)], code: 'hero'));

        self::assertTrue($upgraded->hasNewer());
        self::assertEquals([new NewerVersion(Kind::BlockType, 'heading', 3, 1), new NewerVersion(Kind::BlockType, 'button', 2, 1)], $upgraded->newer);
        self::assertEquals([
            new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'heading', '%version%' => 3, '%code_version%' => 1]),
            new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'button', '%version%' => 2, '%code_version%' => 1]),
        ], $upgraded->newerVersionMessages());
        self::assertSame(3, $upgraded->tree()->blocks[0]->version, 'What was saved with a newer version is kept as it is');
    }

    /**
     * What was newer is known even when an upgrader left the rest out of the stored format, which reading it back
     * refuses
     *
     * @test
     */
    public function it_refuses_to_read_back_what_an_upgrader_left_out_of_the_stored_format(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->willReturn(new Upgraded([['type' => 'heading']], [new NewerVersion(Kind::BlockType, 'button', 2, 1)]));

        $upgraded = (new PresetUpgrader($this->upgrader->reveal()))->upgrade(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 1)], code: 'hero'));

        self::assertCount(1, $upgraded->newer);
        $this->expectException(InvalidStoredTreeException::class);
        $upgraded->tree();
    }
}
