<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Keeps the enabled taxons. Its own parameters start with "cms_visible_", so callers can add theirs
 */
final readonly class VisibleTaxonsQuery implements VisibleTaxonsQueryInterface
{
    /**
     * @param class-string $taxonClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $taxonClass,
    ) {
    }

    public function createQueryBuilder(RenderContext $context): QueryBuilder
    {
        return EntityManagers::of($this->managerRegistry, $this->taxonClass)->createQueryBuilder()
            ->select('taxon')
            ->from($this->taxonClass, 'taxon')
            ->andWhere('taxon.enabled = :cms_visible_enabled')
            ->setParameter('cms_visible_enabled', true, Types::BOOLEAN)
        ;
    }
}
