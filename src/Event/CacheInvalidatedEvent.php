<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

final class CacheInvalidatedEvent
{
    /** @readonly */
    public ElementInterface $element;

    public function __construct(ElementInterface $element)
    {
        $this->element = $element;
    }
}
