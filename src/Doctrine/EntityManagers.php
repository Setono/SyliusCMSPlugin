<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Entries and the rows beside them are mapped with the Doctrine ORM, which transactions, locks and queries need
 *
 * @internal
 */
final class EntityManagers
{
    /**
     * @param class-string $class
     *
     * @throws \LogicException when the Doctrine ORM doesn't manage the class
     */
    public static function of(ManagerRegistry $managerRegistry, string $class): EntityManagerInterface
    {
        $manager = $managerRegistry->getManagerForClass($class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine entity manager manages %s', $class));
        }

        return $manager;
    }
}
