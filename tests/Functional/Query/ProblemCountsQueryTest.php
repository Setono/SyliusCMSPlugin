<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Query;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The stale entries are counted again a batch at a time, on whichever admin page reads the counts first after a
 * deploy, so no page view goes through every entry, and two at once never count the same entries. See "Problems" in
 * docs/spec/admin.md
 */
final class ProblemCountsQueryTest extends KernelTestCase
{
    use Entries;

    /** Another fingerprint than the one the entries were counted with when they were saved */
    private const FINGERPRINT = 'after-a-deploy';

    /**
     * @test
     */
    public function it_counts_at_most_a_batch_of_stale_entries_in_the_order_of_their_ids_and_the_next_call_the_next_batch(): void
    {
        self::bootKernel();
        $pages = [self::createPage('One', 'one'), self::createPage('Two', 'two'), self::createPage('Three', 'three')];

        self::assertSame(['One', 'Two'], self::countStale(2));
        self::assertSame(['Three'], self::countStale(2));
        self::assertSame([], self::countStale(2));

        self::assertEquals([new ProblemCount(1, 0), new ProblemCount(1, 0), new ProblemCount(1, 0)], array_map(self::storedCount(...), $pages));
    }

    /**
     * @test
     */
    public function it_skips_an_entry_another_request_has_claimed_until_its_claim_lapses(): void
    {
        self::bootKernel();
        $claimed = self::createPage('Claimed', 'claimed');
        $lapsed = self::createPage('Lapsed', 'lapsed');
        self::createPage('Stale', 'stale');
        self::setCountedWith($claimed, 'claim:9999999999:0123456789abcdef');
        self::setCountedWith($lapsed, 'claim:0000000001:0123456789abcdef');

        self::assertSame(['Lapsed', 'Stale'], self::countStale(100));
    }

    /**
     * An entry whose content type the code no longer has can't be loaded, so it's left out rather than breaking every
     * admin page that reads the counts
     *
     * @test
     */
    public function it_leaves_out_an_entry_whose_content_type_was_removed(): void
    {
        self::bootKernel();
        $removed = self::createPage('Removed', 'removed');
        self::createPage('Kept', 'kept');
        self::manager()->getConnection()->executeStatement('UPDATE setono_sylius_cms__entry SET content_type = :code WHERE id = :id', ['code' => 'gone', 'id' => $removed->getId()]);
        self::manager()->clear();

        self::assertSame(['Kept'], self::countStale(100));
    }

    /**
     * @test
     */
    public function it_never_overwrites_what_a_write_counted_meanwhile(): void
    {
        self::bootKernel();
        $written = self::createPage('Written', 'written');
        $other = self::createPage('Other', 'other');

        self::query()->countStale(self::FINGERPRINT, 100, static function (array $entries) use ($written): array {
            // A write counts it while the batch is counted
            self::query()->store([(int) $written->getId() => new ProblemCount(0, 3)], self::FINGERPRINT);

            return array_combine(array_map(static fn (EntryInterface $entry): int => (int) $entry->getId(), $entries), array_fill(0, count($entries), new ProblemCount(1, 0)));
        });

        self::assertEquals(new ProblemCount(0, 3), self::storedCount($written));
        self::assertEquals(new ProblemCount(1, 0), self::storedCount($other));
    }

    /**
     * An entry left out of the counts keeps its counts and stays stale, for a later request to count once the claim lapses
     *
     * @test
     */
    public function an_entry_left_out_of_the_counts_keeps_its_counts(): void
    {
        self::bootKernel();
        $page = self::createPage('Page', 'page');
        self::query()->store([(int) $page->getId() => new ProblemCount(2, 2)], 'before-the-deploy');

        self::query()->countStale(self::FINGERPRINT, 100, static fn (array $entries): array => []);

        self::assertEquals(new ProblemCount(2, 2), self::storedCount($page));
    }

    /**
     * It runs on an admin page, which may go on to use the entries it loaded itself
     *
     * @test
     */
    public function it_detaches_the_entries_it_loaded_but_none_the_request_had_loaded_already(): void
    {
        self::bootKernel();
        $loaded = self::createPage('Loaded', 'loaded');
        self::createPage('Not loaded', 'not-loaded');
        $manager = self::manager();
        $manager->clear();
        $loaded = $manager->find(Entry::class, $loaded->getId());
        self::assertNotNull($loaded);

        $counted = [];
        self::query()->countStale(self::FINGERPRINT, 100, static function (array $entries) use (&$counted): array {
            $counted = $entries;

            return [];
        });

        self::assertCount(2, $counted);
        self::assertTrue($manager->contains($loaded));
        self::assertSame($loaded, $counted[0]);
        self::assertFalse($manager->contains($counted[1]));
    }

    /**
     * Counts the stale entries again, at most $limit, with one error each, and answers their titles
     *
     * @return list<string|null>
     */
    private static function countStale(int $limit): array
    {
        $titles = [];
        self::query()->countStale(self::FINGERPRINT, $limit, static function (array $entries) use (&$titles): array {
            $counts = [];
            foreach ($entries as $entry) {
                self::assertInstanceOf(PageInterface::class, $entry);
                $titles[] = $entry->getTranslation('en_US')->getTitle();
                $counts[(int) $entry->getId()] = new ProblemCount(1, 0);
            }

            return $counts;
        });

        return $titles;
    }

    private static function setCountedWith(EntryInterface $entry, string $countedWith): void
    {
        self::manager()->getConnection()->executeStatement('UPDATE setono_sylius_cms__entry SET problems_counted_with = :counted_with WHERE id = :id', ['counted_with' => $countedWith, 'id' => $entry->getId()]);
    }

    private static function storedCount(EntryInterface $entry): ProblemCount
    {
        return self::query()->ofEntries([$entry], [], new \DateTimeImmutable())[$entry->getUuid()] ?? new ProblemCount();
    }

    private static function query(): ProblemCountsQueryInterface
    {
        /** @var ProblemCountsQueryInterface $query */
        $query = self::getContainer()->get(ProblemCountsQueryInterface::class);

        return $query;
    }

    private static function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }
}
