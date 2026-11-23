<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The title and meta description an entry shows in a channel and locale, which its head tags print. See "SEO fields"
 * and "Head tags" in docs/spec/urls-and-seo.md
 */
interface MetaValuesInterface
{
    /**
     * The meta title through the fallback chain, exactly, so editors keep full control. Without one, the title through
     * the chain followed by the channel's name, "<title> | <channel name>", as Sylius titles a product page, or whichever
     * of the two has a value. Null when neither has
     */
    public function title(EntryInterface $entry, ChannelInterface $channel, string $localeCode): ?string;

    /**
     * The meta description, or else the field the content type names in descriptionField, each through the fallback
     * chain. Null when neither has a value, or the content type names no field
     */
    public function description(EntryInterface $entry, string $localeCode): ?string;
}
