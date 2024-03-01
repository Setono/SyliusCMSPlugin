<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * @method PageInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageInterface[]    findAll()
 * @method PageInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface PageRepositoryInterface extends ElementRepositoryInterface
{
    public function findOneBySlug(ChannelInterface $channel, string $locale, string $slug): ?PageInterface;

    public function exists(string $slug): bool;
}
