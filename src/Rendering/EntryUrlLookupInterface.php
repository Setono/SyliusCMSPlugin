<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Looks an entry's URL up. A project can add a cache by decorating it. See "Rendering services" in docs/spec/extending.md
 */
interface EntryUrlLookupInterface
{
    /**
     * $path is "{prefix}/{slug}", or the slug alone for an empty prefix. Includes old slugs
     */
    public function lookup(ChannelInterface $channel, string $localeCode, string $path): ?EntryUrlMatch;
}
