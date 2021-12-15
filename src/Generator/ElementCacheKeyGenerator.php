<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use function get_class;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use function sprintf;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;

final class ElementCacheKeyGenerator implements ElementCacheKeyGeneratorInterface
{
    private ChannelContextInterface $channelContext;

    private LocaleContextInterface $localeContext;

    public function __construct(ChannelContextInterface $channelContext, LocaleContextInterface $localeContext)
    {
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
    }

    public function getCacheKey(
        $element,
        string $elementType = null,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): string {
        $cachePrefix = $element instanceof ElementInterface ? get_class($element) : (string) $elementType;
        $cacheKey = $element instanceof CodeAwareInterface ? $element->getCode() : (string) $element;

        if (null === $channel) {
            $channel = $this->channelContext->getChannel();
        }
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return sprintf('%s_%s_%s_%s', $cachePrefix, $cacheKey, $channel->getCode(), $localeCode);
    }
}
