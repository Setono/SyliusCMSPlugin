<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Problem\ProblemSummaryListener;
use Setono\SyliusCMSPlugin\Problem\ProblemSummaryProviderInterface;

final class ProblemSummaryListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProblemSummaryProviderInterface> */
    private ObjectProphecy $summaries;

    protected function setUp(): void
    {
        $this->summaries = $this->prophesize(ProblemSummaryProviderInterface::class);
    }

    /**
     * @test
     *
     * @dataProvider provideWrites
     *
     * @param array{list<object>, list<object>, list<object>} $scheduled the insertions, updates and deletions
     */
    public function it_drops_the_problem_summary_once_a_flush_that_writes_an_entry_or_its_draft_is_done(array $scheduled): void
    {
        $listener = new ProblemSummaryListener($this->summaries->reveal());

        $this->summaries->clear()->shouldNotBeCalled();
        $listener->onFlush($this->flushing(...$scheduled));
        $this->summaries->checkProphecyMethodsPredictions();

        $this->summaries->clear()->shouldBeCalledOnce();
        $listener->postFlush();

        // The next flush that writes nothing of an entry leaves it
        $listener->onFlush($this->flushing([new EntryPresence(new Page(), 'sylius', 'Sylius', new \DateTimeImmutable())], [], []));
        $listener->postFlush();
    }

    /**
     * @return iterable<string, array{array{list<object>, list<object>, list<object>}}>
     */
    public function provideWrites(): iterable
    {
        yield 'an entry inserted' => [[[new SavedChangeSet(new Page(), 1, '[]', new \DateTimeImmutable()), new Page()], [], []]];
        yield 'an entry updated' => [[[], [new Page()], []]];
        yield 'an entry deleted' => [[[], [], [new Page()]]];
        yield 'a translation' => [[[], [new PageTranslation()], []]];
        yield 'a draft' => [[[new Draft()], [], []]];
        yield 'a channel' => [[[], [], [new EntryChannel(new Page(), 'WEB')]]];
        yield 'an audience group' => [[[new EntryAudienceGroup(new Page(), 'wholesale')], [], []]];
    }

    /**
     * @test
     */
    public function it_leaves_the_summary_after_a_flush_that_writes_nothing_of_an_entry(): void
    {
        $listener = new ProblemSummaryListener($this->summaries->reveal());
        $this->summaries->clear()->shouldNotBeCalled();

        $listener->onFlush($this->flushing([new SavedChangeSet(new Page(), 1, '[]', new \DateTimeImmutable())], [new EntryPresence(new Page(), 'sylius', 'Sylius', new \DateTimeImmutable())], []));
        $listener->postFlush();
        $listener->postFlush();
    }

    /**
     * @param list<object> $insertions
     * @param list<object> $updates
     * @param list<object> $deletions
     */
    private function flushing(array $insertions, array $updates, array $deletions): OnFlushEventArgs
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);
        $unitOfWork->getScheduledEntityInsertions()->willReturn($insertions);
        $unitOfWork->getScheduledEntityUpdates()->willReturn($updates);
        $unitOfWork->getScheduledEntityDeletions()->willReturn($deletions);

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->getUnitOfWork()->willReturn($unitOfWork->reveal());

        return new OnFlushEventArgs($manager->reveal());
    }
}
