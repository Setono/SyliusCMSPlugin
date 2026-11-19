<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Layout\Page\Standard;
use Setono\SyliusCMSPlugin\Preset\InsertTransformer;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\StartingPointTransformer;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyResolverInterface;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Unit\Preset\Fixture\Campaign;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A new entry's tree, built from a starting point: its blocks copied as inserting copies them, in its layout with the
 * layout's field values, or in the content type's default layout without them when that layout is gone. See "Starting
 * points" in docs/spec/layouts-and-presets.md
 */
final class StartingPointTransformerTest extends TestCase
{
    use ProphecyTrait;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const TEXT = '0193a5be-0000-7000-8000-000000000002';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    /** @var ObjectProphecy<TranslationKeyResolverInterface> */
    private ObjectProphecy $translationKeys;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $this->upgrader->upgradeTree(Argument::type('array'), Argument::type('string'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $this->translationKeys = $this->prophesize(TranslationKeyResolverInterface::class);
        $this->translationKeys->resolve(Argument::cetera())->willReturn(null);
    }

    /**
     * @test
     */
    public function it_copies_the_blocks_as_inserting_does_into_the_starting_points_layout_with_its_field_values(): void
    {
        $heading = new StoredBlock(self::HEADING, 'heading', 1, null, ['level' => 'h1', 'text' => ['en_US' => 'Summer sale']]);
        $text = new StoredBlock(self::TEXT, 'rich_text', 1);
        $layout = new StoredLayout('campaign', 2, ['dark' => true, 'tagline' => ['en_US' => 'Up to 50% off']]);

        $created = $this->transformer()->transform(self::startingPoint([$heading, $text], $layout), ['en_US']);

        self::assertFalse($created->refused);
        self::assertSame([], $created->notices);
        self::assertSame(['heading', 'rich_text'], array_map(static fn (StoredBlock $block): string => $block->type, $created->tree->blocks));
        self::assertNotSame(self::HEADING, $created->tree->blocks[0]->key);
        self::assertSame(['level' => 'h1', 'text' => ['en_US' => 'Summer sale']], $created->tree->blocks[0]->fields);
        self::assertEquals(new StoredLayout('campaign', 2, ['dark' => true, 'tagline' => ['en_US' => 'Up to 50% off']]), $created->tree->layout);
    }

    /**
     * @test
     */
    public function it_resolves_translation_keys_in_the_layouts_values_and_drops_the_locales_the_entry_does_not_have(): void
    {
        $this->translationKeys->resolve(new TranslationKey('app.campaign.tagline'), 'da_DK')->willReturn('Op til 50% rabat');
        $state = ['from' => 'en_US', 'fingerprint' => 'abc'];
        $layout = new StoredLayout('campaign', 2, ['dark' => true, 'tagline' => ['$trans' => 'app.campaign.tagline']], ['tagline' => ['da_DK' => $state, 'de_DE' => $state]]);
        $heading = new StoredBlock(self::HEADING, 'heading', 1, null, ['text' => ['de_DE' => 'Sommerschlussverkauf', 'en_US' => 'Summer sale']]);

        $created = $this->transformer()->transform(self::startingPoint([$heading], $layout), ['en_US', 'da_DK']);

        self::assertEquals(new StoredLayout('campaign', 2, ['dark' => true, 'tagline' => ['da_DK' => 'Op til 50% rabat']], ['tagline' => ['da_DK' => $state]]), $created->tree->layout);
        self::assertSame(['text' => ['en_US' => 'Summer sale']], $created->tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_upgrades_the_layout_as_a_tree_of_the_content_type(): void
    {
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->willReturn(new Upgraded([
            'layout' => ['code' => 'campaign', 'version' => 2, 'fields' => ['dark' => true], 'translated' => []],
            'blocks' => [],
        ]));

        $created = $this->transformer()->transform(self::startingPoint([], new StoredLayout('campaign', 1, ['darkMode' => true])), ['en_US']);

        self::assertEquals(new StoredLayout('campaign', 2, ['dark' => true]), $created->tree->layout);
    }

    /**
     * @test
     */
    public function it_refuses_a_starting_point_whose_layout_or_blocks_were_saved_with_a_newer_version_than_the_codes(): void
    {
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::Layout, 'page:campaign', 3, 2)]));

        $created = $this->transformer()->transform(self::startingPoint([new StoredBlock(self::HEADING, 'heading', 1)], new StoredLayout('campaign', 3)), ['en_US']);

        self::assertTrue($created->refused);
        self::assertSame([], $created->tree->blocks);
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'page:campaign', '%version%' => 3, '%code_version%' => 2])], $created->notices);

        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::BlockType, 'heading', 2, 1)]));

        $created = $this->transformer()->transform(self::startingPoint([new StoredBlock(self::HEADING, 'heading', 2)], null), ['en_US']);
        self::assertTrue($created->refused);
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'heading', '%version%' => 2, '%code_version%' => 1])], $created->notices);
    }

    /**
     * @test
     */
    public function it_uses_the_default_layout_without_the_values_when_the_code_no_longer_has_the_starting_points_layout(): void
    {
        $layout = new StoredLayout('retired', 1, ['dark' => true]);

        $created = $this->transformer()->transform(self::startingPoint([new StoredBlock(self::HEADING, 'heading', 1)], $layout), ['en_US']);

        self::assertFalse($created->refused);
        self::assertEquals(new StoredLayout('standard', 1), $created->tree->layout);
        self::assertCount(1, $created->tree->blocks);
    }

    /**
     * @test
     */
    public function it_uses_the_default_layout_without_the_values_when_the_starting_points_layout_is_disabled(): void
    {
        $layout = new StoredLayout('campaign', 2, ['dark' => true]);

        $created = $this->transformer(['page.campaign'])->transform(self::startingPoint([], $layout), ['en_US']);

        self::assertEquals(new StoredLayout('standard', 1), $created->tree->layout);
    }

    /**
     * The create dialog doesn't offer a starting point with what's newer than the code, its layout's version included
     * while that layout is disabled
     *
     * @test
     */
    public function it_refuses_a_starting_point_whose_disabled_layout_was_saved_with_a_newer_version_than_the_codes(): void
    {
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::Layout, 'page:campaign', 3, 2)]));

        self::assertTrue($this->transformer(['page.campaign'])->transform(self::startingPoint([], new StoredLayout('campaign', 3)), ['en_US'])->refused);
    }

    /**
     * @test
     */
    public function it_uses_the_default_layout_when_the_starting_point_has_none(): void
    {
        $created = $this->transformer()->transform(self::startingPoint([], null), ['en_US']);

        self::assertEquals(new StoredLayout('standard', 1), $created->tree->layout);
    }

    /**
     * @test
     */
    public function it_builds_a_tree_without_a_layout_for_a_content_type_without_layouts(): void
    {
        $created = $this->transformer()->transform(self::startingPoint([new StoredBlock(self::HEADING, 'heading', 1)], new StoredLayout('campaign', 2), 'snippet'), ['en_US']);

        self::assertNull($created->tree->layout);
        self::assertCount(1, $created->tree->blocks);
    }

    /**
     * @test
     */
    public function it_refuses_a_block_preset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transformer()->transform(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 1)], code: 'hero'), ['en_US']);
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function startingPoint(array $blocks, ?StoredLayout $layout, string $contentType = 'page'): Preset
    {
        return new Preset('Campaign page', $blocks, startingPoint: new StartingPoint($contentType, $layout), code: 'campaign_page');
    }

    /**
     * @param list<string> $disabledLayouts
     */
    private function transformer(array $disabledLayouts = []): StartingPointTransformer
    {
        $insertTransformer = new InsertTransformer(
            new PresetUpgrader($this->upgrader->reveal()),
            new BlockTypeRegistry(['heading' => Heading::class, 'rich_text' => RichText::class], new FieldReader()),
            $this->translationKeys->reveal(),
            new TranslationKeyValues(new RichTextSchema()),
        );

        return new StartingPointTransformer(
            $insertTransformer,
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'campaign' => Campaign::class]], new FieldReader(), [], $disabledLayouts),
            new PresetUpgrader($this->upgrader->reveal()),
        );
    }
}
