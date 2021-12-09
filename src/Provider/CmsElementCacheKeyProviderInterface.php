<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Provider;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

interface CmsElementCacheKeyProviderInterface
{
    /**
     * @param ElementInterface|string $element The element itself or its identifier
     * @param string|null $elementType The element type (classFQN) in case the first argument is a string
     */
    public function getCacheKey($element, ?string $elementType = null): string;
}
