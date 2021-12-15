<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

interface ElementCacheKeyGeneratorInterface
{
    /**
     * @param ElementInterface|string $element The element itself or its identifier
     * @param string|null $elementType The element type (classFQN) in case the first argument is a string
     * @param ChannelInterface|null $channel The channel to use for cache key. If null, a fallback should be given
     * @param string|null $localeCode The string to use for cache key. If null, a fallback should be assigned
     */
    public function getCacheKey(
        $element,
        string $elementType = null,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): string;
}
