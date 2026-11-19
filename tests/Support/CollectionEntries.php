<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Repository\EntryReferenceRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * What the helpers of the shipped collections, the blog's and the FAQ's, share: saving an entry they created, and
 * publishing it through the publisher, as the builder's Publish does, so its content type fills its values and its
 * categories are written to the reference table, and reading what the reference table holds for an entry
 */
trait CollectionEntries
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * What the reference table holds for the entry: its field and UUID per row, sorted
     *
     * @return list<array{string, string}>
     */
    protected static function referencesOf(EntryInterface $entry): array
    {
        /** @var EntryReferenceRepositoryInterface $repository */
        $repository = self::getContainer()->get(EntryReferenceRepositoryInterface::class);

        $references = array_map(static fn (EntryReferenceInterface $reference): array => [$reference->getField(), $reference->getUuid()], $repository->findByEntry($entry));
        sort($references);

        return $references;
    }

    /**
     * @template T of EntryInterface
     *
     * @param T $entry
     *
     * @return T
     */
    private static function saveCollectionEntry(EntryInterface $entry, bool $published): EntryInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.entry');
        $manager->persist($entry);
        $manager->flush();

        if ($published) {
            /** @var EntryPublisherInterface $publisher */
            $publisher = self::getContainer()->get(EntryPublisherInterface::class);
            $publisher->publish($entry);
        }

        return $entry;
    }
}
