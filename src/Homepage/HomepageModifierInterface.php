<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;

/**
 * Changes the channels pages are the homepages of: "Homepage of", an on-save setting. Everything that changes it goes
 * through it, such as saving the on-save settings, and it holds the rules: a page only becomes the homepage of a
 * channel it's in, and a channel has one homepage. It creates the rows through the homepage resource's factory, so a
 * project that overrides that model gets rows of its own class. See "Homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageModifierInterface
{
    /**
     * Refuses to make the page the homepage of a channel it won't be in, as shoppers of that channel never see it. A
     * channel it has left stays its homepage, which the admin flags until the page is back in the channel or no longer
     * its homepage, so it may stay among the channels
     *
     * @param iterable<string> $channelCodes the channels it's to be the homepage of
     * @param iterable<string> $pageChannelCodes the channels it's to be in
     *
     * @throws InvalidEntrySettingsException naming the channels it won't be in
     */
    public function checkHomepageOf(PageInterface $page, iterable $channelCodes, iterable $pageChannelCodes): void;

    /**
     * Runs writes that change pages' homepages, such as saving the on-save settings, in one transaction of the manager.
     * Two saves that make different pages one channel's homepage at the same moment both find no other page to take it
     * off, so the second one's row breaks the rows' unique channel code, which is refused with a message. The failed
     * flush closed the manager, as any does
     *
     * @param \Closure(): void $writes
     *
     * @throws InvalidEntrySettingsException when another save has just made another page the homepage of one of the
     *                                       channels
     */
    public function inTransaction(EntityManagerInterface $manager, \Closure $writes): void;

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
