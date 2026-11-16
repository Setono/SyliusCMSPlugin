<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\ShareLink;

use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;

/**
 * The URL of a share link, which shows its entry's draft in the shop. See "Share links" in docs/spec/publishing.md
 */
interface ShareLinkUrlGeneratorInterface
{
    /**
     * Null when the entry's draft no longer has a URL in the link's locale
     */
    public function generate(ShareLinkInterface $shareLink): ?string;
}
