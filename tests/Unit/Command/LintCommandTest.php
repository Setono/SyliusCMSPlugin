<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

final class LintCommandTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<WorkingCopyManagerInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->workingCopies = $this->prophesize(WorkingCopyManagerInterface::class);
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
    }

    /**
     * @test
     */
    public function it_succeeds_when_no_kind_finds_a_problem_in_any_entry(): void
    {
        $neverPublished = new Page();
        $published = self::published();
        $this->entries->iterateAll()->willReturn([$neverPublished, $published]);
        $this->workingCopies->getWorkingCopy($neverPublished)->willReturn($neverPublished);
        $this->workingCopies->hasDraft($published)->willReturn(false);
        $this->workingCopies->getWorkingCopy($published)->shouldNotBeCalled();
        $this->upgrader->readBlockTree($published)->willReturn(new StoredTree(null, []));
        $this->manager->clear()->shouldNotBeCalled();

        $tester = $this->lint([self::kind([])]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] No problems found.', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_lists_the_problems_of_every_kind_in_each_entrys_published_content_and_draft_and_fails(): void
    {
        $neverPublished = new Page();
        $clean = self::published();
        $published = self::published();
        $publishedTree = new StoredTree(null, [new StoredBlock(self::KEY, 'promo', 1)]);
        $draft = clone $published;

        $this->entries->iterateAll()->willReturn([$neverPublished, $clean, $published]);
        $this->workingCopies->getWorkingCopy($neverPublished)->willReturn($neverPublished);
        $this->upgrader->readBlockTree($clean)->willReturn(new StoredTree(null, []));
        $this->workingCopies->hasDraft($clean)->willReturn(false);
        $this->upgrader->readBlockTree($published)->willReturn($publishedTree);
        $this->workingCopies->hasDraft($published)->willReturn(true);
        $this->workingCopies->getWorkingCopy($published)->willReturn($draft);

        // The published content is a copy of the entry with its upgraded tree, so the entry itself is left as it is
        $isPublishedContent = static fn (EntryInterface $entry): bool => $entry !== $published && $entry->getBlockTree() == $publishedTree;
        $tester = $this->lint([
            self::kind([
                [static fn (EntryInterface $entry): bool => $entry === $neverPublished, new Problem('first', Severity::Warning, $neverPublished, new TranslatableMessage('In the never-published one'), block: self::KEY)],
                [$isPublishedContent, new Problem('first', Severity::Error, $published, new TranslatableMessage('In the published content'), field: 'text')],
            ]),
            self::kind([
                [static fn (EntryInterface $entry): bool => $entry === $draft, new Problem('second', Severity::Error, $draft, new TranslatableMessage('In the draft of %title%', ['%title%' => 'About us']), block: self::KEY, field: 'text', locale: 'da_DK')],
            ]),
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNotEquals($publishedTree, $published->getBlockTree());
        self::assertSame([
            'Entry Content Block Field Locale Problem',
            sprintf('%s draft %s In the never-published one', $neverPublished->getUuid(), self::KEY),
            sprintf('%s published text In the published content', $published->getUuid()),
            sprintf('%s draft %s text da_DK In the draft of About us', $published->getUuid(), self::KEY),
            '[ERROR] Found 3 problems in 2 entries.',
        ], self::lines($tester));
    }

    /**
     * @test
     */
    public function it_counts_one_problem_in_one_entry(): void
    {
        $page = new Page();
        $this->entries->iterateAll()->willReturn([$page]);
        $this->workingCopies->getWorkingCopy($page)->willReturn($page);

        $tester = $this->lint([self::kind([[static fn (): bool => true, new Problem('first', Severity::Error, $page, new TranslatableMessage('Wrong'))]])]);

        self::assertStringContainsString('[ERROR] Found 1 problem in 1 entry.', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_drops_the_entries_it_has_checked_every_hundred_entries(): void
    {
        $pages = [];
        for ($i = 0; $i < 201; ++$i) {
            $pages[] = new Page();
        }

        $this->entries->iterateAll()->willReturn($pages);
        $this->workingCopies->getWorkingCopy(Argument::any())->willReturnArgument(0);
        $this->manager->clear()->shouldBeCalledTimes(2);

        self::assertSame(Command::SUCCESS, $this->lint([])->getStatusCode());
    }

    /**
     * @param list<ProblemKindInterface> $kinds
     */
    private function lint(array $kinds): CommandTester
    {
        $tester = new CommandTester(new LintCommand(
            $this->entries->reveal(),
            $this->workingCopies->reveal(),
            $this->upgrader->reveal(),
            new Translator('en'),
            $this->manager->reveal(),
            $kinds,
        ));
        $tester->execute([], ['decorated' => false]);

        return $tester;
    }

    /**
     * A kind that finds each problem in the entries its condition matches
     *
     * @param list<array{\Closure(EntryInterface): bool, Problem}> $problems
     */
    private static function kind(array $problems): ProblemKindInterface
    {
        return new class($problems) implements ProblemKindInterface {
            /**
             * @param list<array{\Closure(EntryInterface): bool, Problem}> $problems
             */
            public function __construct(private readonly array $problems)
            {
            }

            public function findIn(EntryInterface $entry): iterable
            {
                foreach ($this->problems as [$matches, $problem]) {
                    if ($matches($entry)) {
                        yield $problem;
                    }
                }
            }
        };
    }

    private static function published(): Page
    {
        $page = new Page();
        $page->publish(new \DateTimeImmutable());

        return $page;
    }

    /**
     * The output's lines with text, each with single spaces, without the table's borders
     *
     * @return list<string>
     */
    private static function lines(CommandTester $tester): array
    {
        $lines = [];
        foreach (explode("\n", $tester->getDisplay()) as $line) {
            $line = trim((string) preg_replace('/\s+/', ' ', $line));
            if ('' !== $line && 1 !== preg_match('/^[- ]+$/', $line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
