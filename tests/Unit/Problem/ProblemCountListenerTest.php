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
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\ChannelAndTaxonReferences;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\CustomerGroupReferences;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Product\Model\Product;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

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
            /** @var list<EntryInterface> $entries */
            $entries = $arguments[0];
            $updated[] = $entries;
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
    public function it_counts_nothing_of_a_translation_of_something_other_than_an_entry(): void
    {
        $translation = new PageTranslation();
        $translation->setLocale('en_US');
        (new Product())->addTranslation($translation);
        $listener = new ProblemCountListener($this->updater->reveal());

        $listener->onFlush($this->flushing([$translation], [], []));
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
    public function it_counts_the_entries_that_reference_what_a_flush_writes_as_the_kinds_about_it_find_them(): void
    {
        $page = new Page();
        $referencing = new Page();
        $deleted = self::customerGroup('wholesale');
        $added = self::customerGroup('retail');
        $customerGroups = new CustomerGroupReferences([$referencing, $page]);
        $channelsAndTaxons = new ChannelAndTaxonReferences([new Page()]);
        $listener = new ProblemCountListener($this->updater->reveal(), [$customerGroups, $channelsAndTaxons], [CustomerGroupInterface::class, ChannelInterface::class, TaxonInterface::class]);

        $listener->onFlush($this->flushing([$added], [$page], [$deleted]));
        self::assertSame([], $customerGroups->asked, 'Nothing is asked before the flush is done');
        $listener->postFlush();

        self::assertSame([[$added, $deleted]], $customerGroups->asked);
        self::assertSame([], $channelsAndTaxons->asked, 'A kind is asked only about what it references');
        self::assertSame([[$page, $referencing]], $this->updated);
    }

    /**
     * @test
     */
    public function it_asks_a_kind_about_all_it_references_that_a_flush_writes_at_once(): void
    {
        $taxon = new Taxon();
        $channel = new Channel();
        $customerGroups = new CustomerGroupReferences();
        $channelsAndTaxons = new ChannelAndTaxonReferences();
        $listener = new ProblemCountListener($this->updater->reveal(), [$customerGroups, $channelsAndTaxons], [CustomerGroupInterface::class, ChannelInterface::class, TaxonInterface::class]);

        $listener->onFlush($this->flushing([$taxon], [new Page()], [$channel]));
        $listener->postFlush();

        self::assertSame([[$taxon, $channel]], $channelsAndTaxons->asked);
        self::assertSame([], $customerGroups->asked);
    }

    /**
     * @test
     */
    public function it_asks_no_kind_when_a_flush_writes_nothing_they_reference(): void
    {
        $page = new Page();
        $kinds = new class([new CustomerGroupReferences([new Page()])]) implements \IteratorAggregate {
            public int $built = 0;

            /**
             * @param list<CustomerGroupReferences> $kinds
             */
            public function __construct(private readonly array $kinds)
            {
            }

            public function getIterator(): \Iterator
            {
                ++$this->built;

                return new \ArrayIterator($this->kinds);
            }
        };
        $listener = new ProblemCountListener($this->updater->reveal(), $kinds, [CustomerGroupInterface::class]);

        $listener->onFlush($this->flushing([], [$page, new Channel()], []));
        $listener->postFlush();
        $listener->postFlush();

        self::assertSame(0, $kinds->built, 'No kind is even built');
        self::assertSame([[$page]], $this->updated);
    }

    /**
     * @test
     */
    public function it_forgets_what_a_flush_that_failed_wrote(): void
    {
        $customerGroups = new CustomerGroupReferences([new Page()]);
        $listener = new ProblemCountListener($this->updater->reveal(), [$customerGroups], [CustomerGroupInterface::class]);
        $listener->onFlush($this->flushing([new Page(), self::customerGroup('wholesale')], [], []));

        $listener->reset();
        $listener->postFlush();

        self::assertSame([], $this->updated);
        self::assertSame([], $customerGroups->asked);
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

    private static function customerGroup(string $code): CustomerGroup
    {
        $customerGroup = new CustomerGroup();
        $customerGroup->setCode($code);

        return $customerGroup;
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
