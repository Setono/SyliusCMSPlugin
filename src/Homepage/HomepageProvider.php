<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Looks the homepage up with one query: the visible page with a homepage row of the context's channel
 */
final readonly class HomepageProvider implements HomepageProviderInterface
{
    /**
     * The code of Page, the built-in content type whose entries can be homepages
     */
    private const PAGE = 'page';

    public function __construct(private VisibleEntriesQueryInterface $visibleEntries)
    {
    }

    public function getHomepage(RenderContext $context): ?PageInterface
    {
        $page = $this->visibleEntries->createQueryBuilder(self::PAGE, $context)
            ->innerJoin('entry.homepages', 'homepage', 'WITH', 'homepage.channelCode = :homepage_channel')
            ->setParameter('homepage_channel', (string) $context->channel->getCode())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $page instanceof PageInterface ? $page : null;
    }
}
