<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Provider;

use function get_class;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use function sprintf;
use Sylius\Component\Resource\Model\CodeAwareInterface;

final class CmsElementCacheKeyProvider implements CmsElementCacheKeyProviderInterface
{
    public function getCacheKey($element, ?string $elementType = null): string
    {
        $cachePrefix = $element instanceof ElementInterface ? get_class($element) : (string) $elementType;
        $cacheKey = $element instanceof CodeAwareInterface ? $element->getCode() : (string) $element;

        return sprintf('%s_%s', $cachePrefix, $cacheKey);
    }
}
