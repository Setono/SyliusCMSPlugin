<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Query;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The locales each entry is complete in are stored with it, so the Overview's Translations counts them per channel and
 * locale without going through the entries. See "Overview" in docs/spec/admin.md
 */
final class CompletenessQueryTest extends KernelTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_counts_per_channel_the_entries_in_it_and_those_complete_in_each_of_its_locales(): void
    {
        self::bootKernel();
        self::createChannel('NORDIC');
        self::createChannel('EMPTY');
        $both = self::createPage('Both', 'both', channels: ['WEB', 'NORDIC']);
        $english = self::createPage('English', 'english', channels: ['WEB']);
        $none = self::createPage('None', 'none', channels: ['WEB', 'NORDIC']);
        $neverCounted = self::createPage('Never counted', 'never-counted', channels: ['WEB']);
        $underscore = self::createPage('Underscore', 'underscore', channels: ['NORDIC']);
        self::query()->store([
            (int) $both->getId() => ['da_DK', 'en_US'],
            (int) $english->getId() => ['en_US'],
            (int) $none->getId() => [],
            // A locale code is matched as it is, its _ included
            (int) $underscore->getId() => ['daXDK'],
        ]);
        self::setCompleteLocales($neverCounted, null);

        self::assertSame([
            'WEB' => ['entries' => 4, 'complete' => ['en_US' => 2, 'da_DK' => 1]],
            'NORDIC' => ['entries' => 3, 'complete' => ['en_US' => 1, 'da_DK' => 1]],
            'EMPTY' => ['entries' => 0, 'complete' => ['en_US' => 0]],
        ], self::query()->count(['WEB' => ['en_US', 'da_DK'], 'NORDIC' => ['en_US', 'da_DK'], 'EMPTY' => ['en_US']]));
    }

    /**
     * @test
     */
    public function it_counts_nothing_for_no_channels(): void
    {
        self::bootKernel();

        self::assertSame([], self::query()->count([]));
    }

    /**
     * @test
     */
    public function it_stores_what_a_later_count_of_the_entry_finds_in_place_of_what_it_had(): void
    {
        self::bootKernel();
        $page = self::createPage('Page', 'page', channels: ['WEB']);
        self::query()->store([(int) $page->getId() => ['en_US']]);

        self::query()->store([(int) $page->getId() => []]);

        self::assertSame(['WEB' => ['entries' => 1, 'complete' => ['en_US' => 0]]], self::query()->count(['WEB' => ['en_US']]));
    }

    private static function setCompleteLocales(EntryInterface $entry, ?string $completeLocales): void
    {
        self::manager()->getConnection()->executeStatement('UPDATE setono_sylius_cms__entry SET complete_locales = :complete WHERE id = :id', ['complete' => $completeLocales, 'id' => $entry->getId()]);
    }

    private static function query(): CompletenessQueryInterface
    {
        /** @var CompletenessQueryInterface $query */
        $query = self::getContainer()->get(CompletenessQueryInterface::class);

        return $query;
    }

    private static function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }
}
