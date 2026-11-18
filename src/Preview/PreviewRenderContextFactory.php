<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The render context of a preview that's being made, such as by the builder's preview token or a new share link, in
 * an admin request, which Sylius's shop contexts don't describe: the preview's channel, in its base currency, its
 * locale, Shown to and Date, with no customer, as ShopRenderContextFactory makes it once the token is in the request.
 * It picks and checks a snippet's sample object as the preview will show it
 *
 * @internal
 */
final readonly class PreviewRenderContextFactory
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function create(Preview $preview, ChannelInterface $channel): RenderContext
    {
        return new RenderContext(
            $channel,
            $preview->localeCode,
            (string) $channel->getBaseCurrency()?->getCode(),
            null,
            $preview->shownTo ?? CustomerGroupValue::GUEST,
            $preview->date ?? $this->clock->now(),
            [],
            $preview->getMode(),
        );
    }
}
