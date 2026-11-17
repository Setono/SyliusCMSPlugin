<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The media folder resource's repository
 */
class MediaFolderRepository extends EntityRepository implements MediaFolderRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?MediaFolderInterface
    {
        $folder = $this->findOneBy(['uuid' => $uuid]);

        return $folder instanceof MediaFolderInterface ? $folder : null;
    }

    public function findAllByName(): array
    {
        /** @var list<MediaFolderInterface> $folders */
        $folders = $this->createQueryBuilder('o')
            ->addOrderBy('o.name', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $folders;
    }
}
