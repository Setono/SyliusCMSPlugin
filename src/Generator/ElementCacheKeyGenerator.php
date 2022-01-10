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
use Symfony\Contracts\Cache\ItemInterface;

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
        $cacheKey = self::resolveCacheKey($element);

        if (null === $channel) {
            $channel = $this->channelContext->getChannel();
        }

        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        $cacheKey = sprintf('%s_%s_%s_%s', $cachePrefix, $cacheKey, (string) $channel->getCode(), $localeCode);

        return preg_replace(
            sprintf('/[%s]+/', preg_quote(ItemInterface::RESERVED_CHARACTERS, '/')),
            '_',
            $cacheKey
        );
    }

    /**
     * @param ElementInterface|string|mixed $element
     */
    private static function resolveCacheKey($element): string
    {
        if ($element instanceof CodeAwareInterface) {
            return (string) $element->getCode();
        }

        if ($element instanceof ElementInterface) {
            return $element->getIdentifier();
        }

        if (is_string($element)) {
            return $element;
        }

        throw new \InvalidArgumentException(sprintf('The element must be either a string or %s', ElementInterface::class));
    }
}
