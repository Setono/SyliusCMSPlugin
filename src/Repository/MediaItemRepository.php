<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The media item resource's repository. Its queries work on MySQL and PostgreSQL alike
 */
class MediaItemRepository extends EntityRepository implements MediaItemRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?MediaItemInterface
    {
        $mediaItem = $this->findOneBy(['uuid' => $uuid]);

        return $mediaItem instanceof MediaItemInterface ? $mediaItem : null;
    }

    public function findByUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<MediaItemInterface> $mediaItems */
        $mediaItems = $this->createQueryBuilder('o')
            ->andWhere('o.uuid IN (:uuids)')
            ->setParameter('uuids', $uuids)
            ->getQuery()
            ->getResult()
        ;

        return $mediaItems;
    }

    public function findExistingUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<string> $existing */
        $existing = $this->createQueryBuilder('o')
            ->select('o.uuid')
            ->andWhere('o.uuid IN (:uuids)')
            ->setParameter('uuids', $uuids)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return $existing;
    }

    public function findOneByChecksum(string $checksum): ?MediaItemInterface
    {
        $mediaItem = $this->createQueryBuilder('o')
            ->andWhere('o.checksum = :checksum')
            ->setParameter('checksum', $checksum)
            ->addOrderBy('o.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $mediaItem instanceof MediaItemInterface ? $mediaItem : null;
    }

    public function findOneByPath(string $path): ?MediaItemInterface
    {
        $mediaItem = $this->findOneBy(['path' => $path]);

        return $mediaItem instanceof MediaItemInterface ? $mediaItem : null;
    }

    public function findAllInUploadOrder(): array
    {
        /** @var list<MediaItemInterface> $mediaItems */
        $mediaItems = $this->createQueryBuilder('o')->orderBy('o.id')->getQuery()->getResult();

        return $mediaItems;
    }

    public function findInFolders(array $folders): array
    {
        if ([] === $folders) {
            return [];
        }

        /** @var list<MediaItemInterface> $mediaItems */
        $mediaItems = $this->createQueryBuilder('o')
            ->andWhere('o.folder IN (:folders)')
            ->setParameter('folders', $folders)
            ->orderBy('o.id')
            ->getQuery()
            ->getResult()
        ;

        return $mediaItems;
    }

    public function createLibraryPaginator(MediaFilter $filter): Pagerfanta
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->addOrderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
        ;

        if (null !== $filter->folder) {
            $queryBuilder->andWhere('o.folder = :folder')->setParameter('folder', $filter->folder);
        } elseif ($filter->unsorted) {
            $queryBuilder->andWhere('o.folder IS NULL');
        }

        if ([] !== $filter->kinds) {
            $queryBuilder->andWhere('o.kind IN (:kinds)')->setParameter('kinds', array_map(static fn (MediaKind $kind): string => $kind->value, $filter->kinds));
        }

        if (null !== $filter->uuids) {
            $queryBuilder->andWhere('o.uuid IN (:uuids)')->setParameter('uuids', [] === $filter->uuids ? [''] : $filter->uuids);
        }

        $search = null === $filter->search ? '' : trim($filter->search);
        if ('' !== $search) {
            $this->search($queryBuilder, $search);
        }

        if (null !== $filter->missingAltIn) {
            $this->missingAlt($queryBuilder, $filter->missingAltIn);
        }

        if ($filter->unused) {
            $this->unused($queryBuilder);
        }

        /** @var Pagerfanta<MediaItemInterface> $paginator */
        $paginator = new Pagerfanta(new QueryAdapter($queryBuilder, false, false));

        return $paginator;
    }

    /**
     * The name, the original filename or the alt text in any locale contains the search, ignoring case
     */
    private function search(QueryBuilder $queryBuilder, string $search): void
    {
        $altText = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from($this->getClassMetadata()->getAssociationTargetClass('translations'), 'searchedTranslation')
            ->andWhere('searchedTranslation.translatable = o')
            ->andWhere('LOWER(searchedTranslation.altText) LIKE :search ' . SearchPattern::ESCAPE)
        ;

        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(
                'LOWER(o.name) LIKE :search ' . SearchPattern::ESCAPE,
                'LOWER(o.originalFilename) LIKE :search ' . SearchPattern::ESCAPE,
                $queryBuilder->expr()->exists($altText->getDQL()),
            ))
            ->setParameter('search', SearchPattern::containing($search))
        ;
    }

    /**
     * No use in the usage index, which names media items by UUID
     */
    private function unused(QueryBuilder $queryBuilder): void
    {
        $use = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(MediaUsageInterface::class, 'mediaUsage')
            ->andWhere('mediaUsage.mediaItemUuid = o.uuid')
        ;

        $queryBuilder->andWhere($queryBuilder->expr()->not($queryBuilder->expr()->exists($use->getDQL())));
    }

    /**
     * Images without alt text in one or more of the locales. A locale without alt text has no translation row
     *
     * @param list<string> $localeCodes
     */
    private function missingAlt(QueryBuilder $queryBuilder, array $localeCodes): void
    {
        $queryBuilder->andWhere('o.kind = :missingAltKind')->setParameter('missingAltKind', MediaKind::Image->value);
        if ([] === $localeCodes) {
            // No locale can miss it
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $withAltText = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(altTranslation.id)')
            ->from($this->getClassMetadata()->getAssociationTargetClass('translations'), 'altTranslation')
            ->andWhere('altTranslation.translatable = o')
            ->andWhere('altTranslation.locale IN (:missingAltLocales)')
            ->andWhere('altTranslation.altText IS NOT NULL')
        ;

        $queryBuilder
            ->andWhere(sprintf('(%s) < :missingAltLocaleCount', $withAltText->getDQL()))
            ->setParameter('missingAltLocales', array_values(array_unique($localeCodes)))
            ->setParameter('missingAltLocaleCount', count(array_unique($localeCodes)))
        ;
    }
}
