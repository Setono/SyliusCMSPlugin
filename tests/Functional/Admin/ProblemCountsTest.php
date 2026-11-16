<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprintInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The lists' problem marker, their Needs attention filter and the Content badge read counts stored with the entries,
 * which a write counts again for the entries it touches, so no admin page goes through every entry. See "Problems" in
 * docs/spec/admin.md
 */
final class ProblemCountsTest extends WebTestCase
{
    use Entries;

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function an_autosave_counts_the_problems_of_the_entry_it_saves_and_no_other_entrys(): void
    {
        self::bootKernel();
        $saved = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock(self::PROMO, 'promo', 1)]);
        $other = self::createPage('Contact', 'contact-us', 'Hello');
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($saved));
        // As if the other one had problems, so a count of it would show
        self::query()->store([(int) $other->getId() => new ProblemCount(5, 4)], self::fingerprint());

        self::manager()->clear();
        $saved = self::entry($saved->getUuid());
        self::saveChanges($saved, [['op' => 'remove_block', 'key' => self::PROMO]]);

        self::assertEquals(new ProblemCount(), self::storedCount($saved));
        self::assertEquals(new ProblemCount(5, 4), self::storedCount($other));
        self::assertFalse(self::manager()->getUnitOfWork()->tryGetById($other->getId(), Entry::class), 'The other entry isn\'t loaded');
    }

    private static function storedCount(EntryInterface $entry): ProblemCount
    {
        /** @var ProblemCountProviderInterface $provider */
        $provider = self::getContainer()->get(ProblemCountProviderInterface::class);

        return $provider->getCounts([$entry])[$entry->getUuid()];
    }

    private static function query(): ProblemCountsQueryInterface
    {
        /** @var ProblemCountsQueryInterface $query */
        $query = self::getContainer()->get(ProblemCountsQueryInterface::class);

        return $query;
    }

    private static function fingerprint(): string
    {
        /** @var ProblemCountFingerprintInterface $fingerprint */
        $fingerprint = self::getContainer()->get(ProblemCountFingerprintInterface::class);

        return $fingerprint->get();
    }

    private static function entry(string $uuid): EntryInterface
    {
        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);
        $entry = $entries->findOneByUuid($uuid);
        self::assertNotNull($entry);

        return $entry;
    }

    private static function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }
}
