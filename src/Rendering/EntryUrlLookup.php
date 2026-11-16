<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Looks a URL up with one indexed query: for each content type with its own URL whose prefix fits the path,
 * an entry of that type in the channel with the rest of the path as its slug in the locale. It selects scalars, not entities,
 * because it runs in the route condition, before Sylius's locale context is set
 */
final readonly class EntryUrlLookup implements EntryUrlLookupInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function lookup(ChannelInterface $channel, string $localeCode, string $path): ?EntryUrlMatch
    {
        $slugs = EntryPath::slugs($this->contentTypes->all(), $path);
        if ([] === $slugs) {
            return null;
        }

        $manager = EntityManagers::of($this->managerRegistry, Entry::class);

        // A URL is unique per channel and locale, so entries with no channel in common can share one
        $query = $manager->getConnection()->createQueryBuilder()
            ->select('entry.uuid', 'entry.content_type')
            ->from($manager->getClassMetadata(Entry::class)->getTableName(), 'entry')
            ->innerJoin('entry', $manager->getClassMetadata(EntryTranslation::class)->getTableName(), 'translation', 'translation.translatable_id = entry.id')
            ->innerJoin('entry', $manager->getClassMetadata(EntryChannelInterface::class)->getTableName(), 'entry_channel', 'entry_channel.entry_id = entry.id')
            ->andWhere('translation.locale = :locale')
            ->andWhere('entry_channel.code = :channel')
            ->setParameter('locale', $localeCode)
            ->setParameter('channel', (string) $channel->getCode())
            ->orderBy('entry.id')
            ->setMaxResults(1)
        ;

        $conditions = [];
        foreach (array_keys($slugs) as $index => $contentType) {
            $conditions[] = sprintf('(entry.content_type = :content_type_%1$d AND translation.slug = :slug_%1$d)', $index);
            $query->setParameter('content_type_' . $index, $contentType);
            $query->setParameter('slug_' . $index, $slugs[$contentType]);
        }

        $query->andWhere(implode(' OR ', $conditions));

        $row = $query->executeQuery()->fetchAssociative();
        if (false === $row || !is_string($row['uuid']) || !is_string($row['content_type'])) {
            return null;
        }

        return new EntryUrlMatch($row['uuid'], $row['content_type']);
    }
}
