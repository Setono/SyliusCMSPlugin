<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * Changes the channels pages are the homepages of: "Homepage of", an on-save setting. Everything that changes it goes
 * through it, such as saving the on-save settings. It creates the rows through the homepage resource's factory, so a
 * project that overrides that model gets rows of its own class. See "Homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageModifierInterface
{
    /**
     * Makes the page the homepage of exactly these channels. A channel has at most one homepage, which the rows' unique
     * channel code holds to, so take the channels off the pages that are their homepages now first, with
     * takeOffOtherPages(), and flush that before this
     *
     * @param iterable<string> $channelCodes
     */
    public function setHomepageOf(PageInterface $page, iterable $channelCodes): void;

    /**
     * Takes these channels off the pages other than $page that are their homepages now
     *
     * @param iterable<string> $channelCodes
     *
     * @return list<PageInterface> the pages that lost a channel, each once
     */
    public function takeOffOtherPages(PageInterface $page, iterable $channelCodes): array;
}
