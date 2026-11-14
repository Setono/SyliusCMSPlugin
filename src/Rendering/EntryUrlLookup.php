<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Looks a URL up with one indexed query: for each content type with its own URL whose prefix fits the path,
 * an entry of that type with the rest of the path as its slug in the locale. It selects scalars, not entities,
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
        $slugs = $this->slugsByContentType($path);
        if ([] === $slugs) {
            return null;
        }

        $manager = $this->managerRegistry->getManagerForClass(Entry::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('%s must be managed by the Doctrine ORM', Entry::class));
        }

        $query = $manager->getConnection()->createQueryBuilder()
            ->select('entry.uuid', 'entry.content_type')
            ->from($manager->getClassMetadata(Entry::class)->getTableName(), 'entry')
            ->innerJoin('entry', $manager->getClassMetadata(EntryTranslation::class)->getTableName(), 'translation', 'translation.translatable_id = entry.id')
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $localeCode)
            ->orderBy('entry.id')
            ->setMaxResults(1)
        ;

        // Entries are in every channel until channels arrive with the on-save settings, so the channel doesn't narrow the lookup yet
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

    /**
     * @return array<string, string> content type code => the slug the path holds for it
     */
    private function slugsByContentType(string $path): array
    {
        $slugs = [];

        foreach ($this->contentTypes->all() as $contentType) {
            if (null === $contentType->prefix) {
                continue;
            }

            if ('' === $contentType->prefix) {
                $slugs[$contentType->code] = $path;

                continue;
            }

            if (str_starts_with($path, $contentType->prefix . '/')) {
                $slugs[$contentType->code] = substr($path, strlen($contentType->prefix) + 1);
            }
        }

        return $slugs;
    }
}
