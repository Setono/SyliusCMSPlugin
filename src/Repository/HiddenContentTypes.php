<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * Leaves the entries of the content types switched off in setono_sylius_cms.disabled.content_types out of a query, as
 * the admin's lists, the Overview's panels and the Content badge do, since their overview pages answer 404. The classes
 * go in the DQL from the discriminator map, never from a request, because Doctrine's paginator counts without
 * resolving an INSTANCE OF parameter to the discriminator values
 *
 * @internal
 */
final class HiddenContentTypes
{
    private function __construct()
    {
    }

    /**
     * @param ClassMetadata<object> $entries the entries' class metadata, whose discriminator map names each content type's model
     * @param list<string> $hidden the codes of the content types switched off
     */
    public static function exclude(QueryBuilder $queryBuilder, string $alias, ClassMetadata $entries, array $hidden): QueryBuilder
    {
        foreach ($hidden as $code) {
            $class = $entries->discriminatorMap[$code] ?? null;
            if (null !== $class) {
                $queryBuilder->andWhere(sprintf('%s NOT INSTANCE OF %s', $alias, $class));
            }
        }

        return $queryBuilder;
    }
}
