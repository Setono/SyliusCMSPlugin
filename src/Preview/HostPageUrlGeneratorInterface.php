<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The URL of a real shop page a slot renders on, which a snippet's preview and share link show it in: the page of the
 * sample object for a slot that provides its context key, such as the product page of a sample product for
 * product.after_price. A project decorates it for a slot of its own whose page the plugin can't know, such as a store
 * locator's. See "Snippet preview" in docs/spec/builder.md
 */
interface HostPageUrlGeneratorInterface
{
    /**
     * An absolute URL on the router's host, or null when the plugin knows no page that renders the slot, such as the
     * checkout's sidebar, which needs a cart. The preview then shows the snippet on a plain page
     *
     * @param object|null $sample the sample object, of one of the context keys the slot provides
     */
    public function generate(SlotMetadata $slot, ?object $sample, ChannelInterface $channel, string $localeCode): ?string;
}
