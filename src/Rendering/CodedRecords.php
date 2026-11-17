<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * What the reference loaders of Sylius records referred to by code, products and taxons, share
 *
 * @internal
 */
final class CodedRecords
{
    private function __construct()
    {
    }

    /**
     * @template T of ProductInterface|TaxonInterface
     *
     * @param iterable<T> $records
     *
     * @return array<string, T>
     */
    public static function byCode(iterable $records): array
    {
        $byCode = [];
        foreach ($records as $record) {
            $byCode[(string) $record->getCode()] = $record;
        }

        return $byCode;
    }

    /**
     * The codes a record of the class has, of those given, whether the shopper may see it or not
     *
     * @param class-string $class
     * @param non-empty-list<string> $codes
     *
     * @return list<string>
     */
    public static function existing(ManagerRegistry $managerRegistry, string $class, array $codes): array
    {
        /** @var list<string> $existing */
        $existing = EntityManagers::of($managerRegistry, $class)->createQueryBuilder()
            ->select('record.code')
            ->from($class, 'record')
            ->andWhere('record.code IN (:codes)')
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return $existing;
    }
}
