<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Filters as Sylius's shop listing and product page do, in SQL that MySQL and PostgreSQL both run. The translation is
 * a subquery rather than a join, so each product is selected once whatever a caller joins. Its own parameters and
 * aliases start with "cms_visible_", so callers can add theirs
 */
final readonly class VisibleProductsQuery implements VisibleProductsQueryInterface
{
    /**
     * @param class-string $productClass
     * @param class-string $productTranslationClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $productClass,
        private string $productTranslationClass,
    ) {
    }

    public function createQueryBuilder(RenderContext $context): QueryBuilder
    {
        return EntityManagers::of($this->managerRegistry, $this->productClass)->createQueryBuilder()
            ->select('product')
            ->from($this->productClass, 'product')
            ->andWhere('product.enabled = :cms_visible_enabled')
            ->setParameter('cms_visible_enabled', true, Types::BOOLEAN)
            ->andWhere(':cms_visible_channel MEMBER OF product.channels')
            ->setParameter('cms_visible_channel', $context->channel)
            ->andWhere(sprintf(
                'EXISTS (SELECT cms_visible_translation.id FROM %s cms_visible_translation WHERE cms_visible_translation.translatable = product AND cms_visible_translation.locale = :cms_visible_locale)',
                $this->productTranslationClass,
            ))
            ->setParameter('cms_visible_locale', $context->localeCode)
        ;
    }
}
