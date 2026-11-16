<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Problem\ProblemCountListener;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdaterInterface;

final class ProblemCountListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProblemCountUpdaterInterface> */
    private ObjectProphecy $updater;

    /** @var list<list<EntryInterface>> the entries of each update() */
    private array $updated = [];

    protected function setUp(): void
    {
        $this->updater = $this->prophesize(ProblemCountUpdaterInterface::class);
        $updated = &$this->updated;
        $this->updater->update(Argument::any())->will(static function (array $arguments) use (&$updated): void {
            /** @var iterable<EntryInterface> $entries */
            $entries = $arguments[0];
            $updated[] = [...$entries];
        });
    }

    /**
     * @test
     *
     * @dataProvider provideWritesOfAnEntry
     *
     * @param \Closure(Page): array{list<object>, list<object>, list<object>} $scheduled the insertions, updates and deletions
     */
    public function it_counts_the_problems_of_an_entry_a_flush_writes_once_the_flush_is_done(\Closure $scheduled): void
    {
        $page = new Page();
        $listener = new ProblemCountListener($this->updater->reveal());

        $listener->onFlush($this->flushing(...$scheduled($page)));
        self::assertSame([], $this->updated, 'Nothing is counted before the flush is done');

        $listener->postFlush();
        self::assertSame([[$page]], $this->updated);

        // What it wrote is counted once
        $listener->postFlush();
        self::assertSame([[$page]], $this->updated);
    }

    /**
     * @return iterable<string, array{\Closure(Page): array{list<object>, list<object>, list<object>}}>
     */
    public function provideWritesOfAnEntry(): iterable
    {
        yield 'the entry inserted' => [static fn (Page $page): array => [[$page], [], []]];
        yield 'the entry updated' => [static fn (Page $page): array => [[], [$page], []]];
        yield 'a translation inserted' => [static fn (Page $page): array => [[self::translationOf($page)], [], []]];
        yield 'a translation deleted' => [static fn (Page $page): array => [[], [], [self::translationOf($page)]]];
        yield 'its draft written' => [static fn (Page $page): array => [[], [self::draftOf($page)], []]];
        yield 'its draft deleted' => [static fn (Page $page): array => [[], [], [self::draftOf($page)]]];
        yield 'a channel added' => [static fn (Page $page): array => [[new EntryChannel($page, 'WEB')], [], []]];
        yield 'a channel removed' => [static fn (Page $page): array => [[], [], [new EntryChannel($page, 'WEB')]]];
        yield 'an audience group' => [static fn (Page $page): array => [[new EntryAudienceGroup($page, 'wholesale')], [], []]];
        yield 'its scheduled publish' => [static fn (Page $page): array => [[], [self::scheduledPublishOf($page)], []]];
    }

    /**
     * @test
     */
    public function it_counts_each_entry_once_however_much_of_it_a_flush_writes_and_only_the_entries_it_writes(): void
    {
        $page = new Page();
        $other = new Page();
        $listener = new ProblemCountListener($this->updater->reveal());

        $listener->onFlush($this->flushing([self::translationOf($page), new EntryChannel($other, 'WEB')], [$page, self::draftOf($page)], [new EntryChannel($page, 'WEB')]));
        $listener->postFlush();

        self::assertSame([[$page, $other]], $this->updated);
    }

    /**
     * @test
     */
    public function it_counts_nothing_of_an_entry_a_flush_deletes(): void
    {
        $page = new Page();
        $listener = new ProblemCountListener($this->updater->reveal());

        $listener->onFlush($this->flushing([], [self::draftOf($page)], [self::translationOf($page), $page, new EntryChannel($page, 'WEB')]));
        $listener->postFlush();

        self::assertSame([], $this->updated);
    }

    /**
     * @test
     */
    public function it_counts_nothing_after_a_flush_that_writes_nothing_of_an_entry(): void
    {
        $page = new Page();
        $listener = new ProblemCountListener($this->updater->reveal());

        // The builder's presence and its saved change sets are written apart from the entry
        $listener->onFlush($this->flushing([new SavedChangeSet($page, 1, '[]', new \DateTimeImmutable())], [new EntryPresence($page, 'sylius', 'Sylius', new \DateTimeImmutable())], []));
        $listener->postFlush();

        self::assertSame([], $this->updated);
    }

    /**
     * @test
     */
    public function it_forgets_what_a_flush_that_failed_wrote(): void
    {
        $listener = new ProblemCountListener($this->updater->reveal());
        $listener->onFlush($this->flushing([new Page()], [], []));

        $listener->reset();
        $listener->postFlush();

        self::assertSame([], $this->updated);
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

    private static function translationOf(Page $page): PageTranslation
    {
        $translation = new PageTranslation();
        $translation->setLocale('en_US');
        $page->addTranslation($translation);

        return $translation;
    }

    private static function draftOf(Page $page): Draft
    {
        $draft = new Draft();
        $draft->setEntry($page);

        return $draft;
    }

    private static function scheduledPublishOf(Page $page): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($page);

        return $scheduledPublish;
    }
}
