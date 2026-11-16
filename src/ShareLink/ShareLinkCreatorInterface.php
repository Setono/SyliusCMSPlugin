<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\ShareLink;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;

/**
 * Makes share links, from the builder or from Share draft on the entry's overview page.
 * See "Share links" in docs/spec/publishing.md
 */
interface ShareLinkCreatorInterface
{
    /**
     * Stores a share link of the entry's draft in the channel and the locale. Shown to and Date are fixed now, with the
     * preview's starting values (see "Shown to and Date" in docs/spec/targeting.md), and the signed-in admin user is
     * recorded as who made it
     *
     * @throws ShareLinkUnavailableException when the entry has no page to share there: the channel doesn't exist or the
     *                                       entry isn't in it, the channel doesn't have the locale, or the entry's draft
     *                                       has no URL in it
     */
    public function create(EntryInterface $entry, string $channelCode, string $localeCode): ShareLinkInterface;
}
