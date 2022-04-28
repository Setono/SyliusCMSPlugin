<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Sylius\Component\Resource\Model\ResourceInterface;

final class CacheInvalidatedEvent
{
    /** @readonly */
    public ResourceInterface $resource;

    public function __construct(ResourceInterface $resource)
    {
        $this->resource = $resource;
    }
}
