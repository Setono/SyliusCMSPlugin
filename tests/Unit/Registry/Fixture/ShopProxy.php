<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Doctrine\Persistence\Proxy;

/**
 * A Doctrine proxy of a shop that isn't loaded yet: its properties hold their defaults until loading fills them from
 * the row
 *
 * @implements Proxy<Shop>
 */
final class ShopProxy extends Shop implements Proxy
{
    private bool $initialized = false;

    public function __load(): void
    {
        $this->setCity('Aarhus');
        $this->initialized = true;
    }

    public function __isInitialized(): bool
    {
        return $this->initialized;
    }
}
