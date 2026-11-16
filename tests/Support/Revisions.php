<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests that need revisions without publishing through the builder, such as revisions
 * stored by an older version of the code
 */
trait Revisions
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Stores a revision of the entry whose content is the entry's rows with these blocks, as stored
     *
     * @param list<StoredBlock> $blocks
     */
    protected static function storeRevision(EntryInterface $entry, array $blocks, string $publishedAt = 'now', ?string $publishedBy = 'John Doe'): RevisionInterface
    {
        $content = clone $entry;
        $content->setBlockTree(new StoredTree($entry->getBlockTree()->layout, $blocks));

        /** @var EntryNormalizer $normalizer */
        $normalizer = self::getContainer()->get(EntryNormalizer::class);
        /** @var FactoryInterface<RevisionInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.revision');

        $revision = $factory->createNew();
        $revision->setEntry($entry);
        $revision->setContent(CanonicalJson::encode($normalizer->normalize($content)));
        $revision->setPublishedAt(new \DateTimeImmutable($publishedAt, new \DateTimeZone('UTC')));
        $revision->setPublishedBy($publishedBy);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.revision');
        $manager->persist($revision);
        $manager->flush();

        return $revision;
    }

    /**
     * The entry's revisions as the database holds them, newest first
     *
     * @return list<RevisionInterface>
     */
    protected static function revisionsOf(EntryInterface $entry): array
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.revision');
        $manager->clear();

        /** @var RevisionRepositoryInterface $revisions */
        $revisions = self::getContainer()->get(RevisionRepositoryInterface::class);
        $reloaded = $manager->find($entry::class, $entry->getId());
        self::assertInstanceOf(EntryInterface::class, $reloaded);

        return $revisions->findByEntry($reloaded);
    }
}
