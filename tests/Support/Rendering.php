<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

/**
 * The render context a test renders, hydrates or queries in: by default a guest's, now, in the channel WEB, en_US and USD
 */
final class Rendering
{
    private function __construct()
    {
    }

    /**
     * @param array<string, object> $hostPageContext
     */
    public static function context(
        string $localeCode = 'en_US',
        ?ChannelInterface $channel = null,
        ?string $previewMode = null,
        array $hostPageContext = [],
        ?\DateTimeImmutable $now = null,
        string $customerGroup = '@guest',
        ?CustomerInterface $customer = null,
        string $currencyCode = 'USD',
    ): RenderContext {
        return new RenderContext(
            $channel ?? self::channel(),
            $localeCode,
            $currencyCode,
            $customer,
            $customerGroup,
            $now ?? new \DateTimeImmutable(),
            $hostPageContext,
            $previewMode,
        );
    }

    /**
     * The hydration context of a test's render context, with the references loaded before rendering
     */
    public static function hydrationContext(?RenderContext $renderContext = null, ReferenceMap $references = new ReferenceMap()): HydrationContext
    {
        return new HydrationContext($renderContext ?? self::context(), $references);
    }

    private static function channel(): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }
}
