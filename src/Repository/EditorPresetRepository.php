<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The editor preset resource's repository
 */
class EditorPresetRepository extends EntityRepository implements EditorPresetRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EditorPresetInterface
    {
        $preset = $this->findOneBy(['uuid' => strtolower($uuid)]);

        return $preset instanceof EditorPresetInterface ? $preset : null;
    }

    public function findAllInCreationOrder(): array
    {
        /** @var list<EditorPresetInterface> $presets */
        $presets = $this->createQueryBuilder('o')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $presets;
    }
}
