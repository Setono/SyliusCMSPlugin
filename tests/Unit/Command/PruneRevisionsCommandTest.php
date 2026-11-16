<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Command\PruneRevisionsCommand;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPruner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneRevisionsCommandTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * @dataProvider providePrunes
     *
     * @param list<array{entry: int, id: int}> $revisions
     */
    public function it_deletes_the_revisions_beyond_the_ones_each_entry_keeps_and_says_how_many(array $revisions, string $message): void
    {
        $repository = $this->prophesize(RevisionRepositoryInterface::class);
        $repository->iterateIdsByEntryNewestFirst()->willReturn($revisions);
        $repository->removeByIds(Argument::any())->will(static function (): void {});

        $tester = new CommandTester(new PruneRevisionsCommand(new RevisionPruner($repository->reveal(), 2)));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString($message, (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * @return iterable<string, array{list<array{entry: int, id: int}>, string}>
     */
    public static function providePrunes(): iterable
    {
        yield 'nothing' => [[['entry' => 1, 'id' => 2], ['entry' => 1, 'id' => 1]], '[OK] Nothing to prune: no entry has more than 2 revisions.'];
        yield 'one' => [[['entry' => 1, 'id' => 3], ['entry' => 1, 'id' => 2], ['entry' => 1, 'id' => 1]], '[OK] Deleted 1 revision. Every entry keeps its newest 2.'];
        yield 'several' => [[['entry' => 1, 'id' => 4], ['entry' => 1, 'id' => 3], ['entry' => 1, 'id' => 2], ['entry' => 1, 'id' => 1]], '[OK] Deleted 2 revisions. Every entry keeps its newest 2.'];
    }

    /**
     * @test
     */
    public function it_is_named_as_the_spec_names_it(): void
    {
        $command = new PruneRevisionsCommand(new RevisionPruner($this->prophesize(RevisionRepositoryInterface::class)->reveal(), 50));

        self::assertSame('setono:cms:revisions:prune', $command->getName());
    }
}
