<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Command\UpgradeCommand;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewrite;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewriterInterface;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
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

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->rewriter = $this->prophesize(EntryRewriterInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
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
        $this->rewriter->rewrite($trees)->willReturn(new EntryRewrite(true, false, []));
        $this->rewriter->rewrite($both)->willReturn(new EntryRewrite(true, true, []));
        $this->rewriter->rewrite($newer)->willReturn(new EntryRewrite(false, false, [new NewerVersion(Kind::BlockType, 'teaser', 4, 3)]));
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
        $this->rewriter->rewrite($first)->willReturn(new EntryRewrite(true, true, []));
        $this->rewriter->rewrite($second)->willReturn(new EntryRewrite(false, true, []));

        self::assertStringContainsString('[OK] Upgraded 1 block tree and 2 drafts.', self::display($this->upgrade([])));
    }

    /**
     * @test
     */
    public function it_reports_what_a_dry_run_would_upgrade_and_writes_nothing(): void
    {
        $entry = new Page();
        $this->entries->iterateAll()->willReturn([$entry]);
        $this->rewriter->rewrite($entry)->willReturn(new EntryRewrite(true, true, []));
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
        $this->rewriter->rewrite($entry)->willReturn(new EntryRewrite(false, false, []));

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
        $this->rewriter->rewrite(Argument::any())->willReturn(new EntryRewrite(false, false, []));

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
        ));
        $tester->execute($input);

        return $tester;
    }

    /**
     * The output on one line, as SymfonyStyle wraps long lines
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
