<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The draft resource's repository
 */
class DraftRepository extends EntityRepository implements DraftRepositoryInterface
{
    public function findOneByEntry(EntryInterface $entry): ?DraftInterface
    {
        $draft = $this->findOneBy(['entry' => $entry]);

        return $draft instanceof DraftInterface ? $draft : null;
    }
}
