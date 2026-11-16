<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\HomepageInterface;

/**
 * The channels pages are the homepages of. See "Homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageRepositoryInterface
{
    /**
     * The homepage rows of these channels, with their pages, by channel code. A channel has at most one
     *
     * @param list<string> $channelCodes
     *
     * @return list<HomepageInterface>
     */
    public function findByChannelCodes(array $channelCodes): array;
}
