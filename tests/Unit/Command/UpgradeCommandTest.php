<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Command\UpgradeCommand;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewrite;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewriterInterface;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\PresetRewriter;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UpgradeCommandTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<EntryRewriterInterface> */
    private ObjectProphecy $rewriter;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<EditorPresetRepositoryInterface> */
    private ObjectProphecy $presets;

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->rewriter = $this->prophesize(EntryRewriterInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
        $this->presets = $this->prophesize(EditorPresetRepositoryInterface::class);
        $this->presets->findAllInCreationOrder()->willReturn([]);
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
    }

    /**
     * @test
     */
    public function it_upgrades_and_writes_back_the_editor_presets_that_need_it_in_one_go_and_counts_them(): void
    {
        $this->entries->iterateAll()->willReturn([]);
        $old = self::editorPreset('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a', 1);
        $current = self::editorPreset('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b', 2);
        $this->presets->findAllInCreationOrder()->willReturn([$old, $current]);
        $this->upgrader->upgradeBlocks([self::block(1)])->willReturn(new Upgraded([self::block(2)]));
        $this->upgrader->upgradeBlocks([self::block(2)])->willReturn(new Upgraded([self::block(2)], [new NewerVersion(Kind::BlockType, 'teaser', 4, 3)]));
        $this->manager->flush()->shouldBeCalledTimes(2);
        $this->manager->clear()->shouldBeCalledTimes(2);

        $display = self::display($this->upgrade([]));

        self::assertStringContainsString('Upgraded the editor preset 0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a', $display);
        self::assertStringNotContainsString('Upgraded the editor preset 0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b', $display);
        self::assertStringContainsString('In the editor preset 0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b: The block type "teaser" was saved with version 4, but the code has version 3. It\'s kept as it is', $display);
        self::assertStringContainsString('Upgraded 1 editor preset.', $display);
    }

    /**
     * @test
     */
    public function it_upgrades_and_writes_back_the_block_trees_and_drafts_that_need_it_and_counts_them(): void
    {
        $trees = new Page();
        $both = new Page();
        $newer = new Page();
        $this->entries->iterateAll()->willReturn([$trees, $both, $newer]);
        $this->rewriter->rewrite($trees)->willReturn(new EntryRewrite(true, false, [], 0));
        $this->rewriter->rewrite($both)->willReturn(new EntryRewrite(true, true, [], 0));
        $this->rewriter->rewrite($newer)->willReturn(new EntryRewrite(false, false, [new NewerVersion(Kind::BlockType, 'teaser', 4, 3)], 0));
        $this->manager->flush()->shouldBeCalledOnce();
        $this->manager->clear()->shouldBeCalledOnce();

        $tester = $this->upgrade([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString(sprintf('Upgraded the block tree of the page %s', $trees->getUuid()), $display);
        self::assertStringNotContainsString(sprintf('the draft of the page %s', $trees->getUuid()), $display);
        self::assertStringContainsString(sprintf('Upgraded the block tree of the page %s', $both->getUuid()), $display);
        self::assertStringContainsString(sprintf('Upgraded the draft of the page %s', $both->getUuid()), $display);
        self::assertStringContainsString(sprintf('In the page %s: The block type "teaser" was saved with version 4, but the code has version 3. It\'s kept as it is', $newer->getUuid()), $display);
        self::assertStringContainsString('[OK] Upgraded 2 block trees and 1 draft.', $display);
    }

    /**
     * @test
     */
    public function it_counts_one_block_tree_and_several_drafts(): void
    {
        $first = new Page();
        $second = new Page();
        $this->entries->iterateAll()->willReturn([$first, $second]);
        $this->rewriter->rewrite($first)->willReturn(new EntryRewrite(true, true, [], 0));
        $this->rewriter->rewrite($second)->willReturn(new EntryRewrite(false, true, [], 0));

        self::assertStringContainsString('[OK] Upgraded 1 block tree and 2 drafts.', self::display($this->upgrade([])));
    }

    /**
     * @test
     */
    public function it_upgrades_revisions_and_counts_them_with_the_rest(): void
    {
        $first = new Page();
        $second = new Page();
        $third = new Page();
        $this->entries->iterateAll()->willReturn([$first, $second, $third]);
        $this->rewriter->rewrite($first)->willReturn(new EntryRewrite(true, true, [], 1));
        $this->rewriter->rewrite($second)->willReturn(new EntryRewrite(false, false, [], 2));
        $this->rewriter->rewrite($third)->willReturn(new EntryRewrite(false, false, [], 0));

        $display = self::display($this->upgrade([]));

        self::assertStringContainsString(sprintf('Upgraded 1 revision of the page %s', $first->getUuid()), $display);
        self::assertStringContainsString(sprintf('Upgraded 2 revisions of the page %s', $second->getUuid()), $display);
        self::assertStringNotContainsString($third->getUuid(), $display);
        self::assertStringContainsString('[OK] Upgraded 1 block tree, 1 draft and 3 revisions.', $display);
    }

    /**
     * @test
     */
    public function it_counts_only_what_it_upgraded(): void
    {
        $entry = new Page();
        $this->entries->iterateAll()->willReturn([$entry]);
        $this->rewriter->rewrite($entry)->willReturn(new EntryRewrite(false, false, [], 1));

        $display = self::display($this->upgrade(['--dry-run' => true]));

        self::assertStringContainsString(sprintf('Would upgrade 1 revision of the page %s', $entry->getUuid()), $display);
        self::assertStringContainsString('[OK] Dry run: 1 revision would be upgraded. Nothing was written.', $display);
    }

    /**
     * @test
     */
    public function it_reports_what_a_dry_run_would_upgrade_and_writes_nothing(): void
    {
        $entry = new Page();
        $this->entries->iterateAll()->willReturn([$entry]);
        $this->rewriter->rewrite($entry)->willReturn(new EntryRewrite(true, true, [], 0));
        $this->manager->flush()->shouldNotBeCalled();
        $this->manager->clear()->shouldBeCalledOnce();

        $display = self::display($this->upgrade(['--dry-run' => true]));

        self::assertStringContainsString(sprintf('Would upgrade the block tree of the page %s', $entry->getUuid()), $display);
        self::assertStringContainsString('[OK] Dry run: 1 block tree and 1 draft would be upgraded. Nothing was written.', $display);
    }

    /**
     * @test
     */
    public function it_says_when_everything_is_up_to_date(): void
    {
        $entry = new Page();
        $this->entries->iterateAll()->willReturn([$entry]);
        $this->rewriter->rewrite($entry)->willReturn(new EntryRewrite(false, false, [], 0));

        self::assertStringContainsString('[OK] Everything is up to date.', self::display($this->upgrade([])));
    }

    /**
     * @test
     *
     * @dataProvider provideBatches
     */
    public function it_writes_and_clears_every_100_entries_and_at_the_end(int $count, int $batches): void
    {
        $entries = array_map(static fn (): EntryInterface => new Page(), range(1, $count));
        $this->entries->iterateAll()->willReturn($entries);
        $this->rewriter->rewrite(Argument::any())->willReturn(new EntryRewrite(false, false, [], 0));

        $this->manager->flush()->shouldBeCalledTimes($batches);
        $this->manager->clear()->shouldBeCalledTimes($batches);

        $this->upgrade([]);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideBatches(): iterable
    {
        yield 'fewer than 100' => [99, 1];
        yield '100' => [100, 2];
        yield 'fewer than 200' => [199, 2];
        yield '200' => [200, 3];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function upgrade(array $input): CommandTester
    {
        $tester = new CommandTester(new UpgradeCommand(
            $this->entries->reveal(),
            $this->rewriter->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            ]),
            $this->manager->reveal(),
            $this->presets->reveal(),
            new PresetRewriter($this->upgrader->reveal()),
        ));
        $tester->execute($input);

        return $tester;
    }

    private static function editorPreset(string $uuid, int $version): EditorPreset
    {
        $row = new EditorPreset();
        $row->setUuid($uuid);
        (new EditorPresetContent())->write($row, new Preset('Teaser', [new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'teaser', $version)], changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')));

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private static function block(int $version): array
    {
        return ['key' => '0193a5be-0000-7000-8000-000000000001', 'type' => 'teaser', 'version' => $version, 'style' => null, 'fields' => new \stdClass(), 'translated' => new \stdClass(), 'conditions' => [], 'children' => []];
    }

    /**
     * The output on one line, as SymfonyStyle wraps long lines
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
