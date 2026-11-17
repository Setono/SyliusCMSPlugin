<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The bindable properties the code declares, by what a binding stores: "<context key>.<code>"
 */
interface BindablePropertyRegistryInterface
{
    public function has(string $binding): bool;

    /**
     * @throws UnknownBindablePropertyException
     */
    public function get(string $binding): BindablePropertyMetadata;

    /**
     * @return list<BindablePropertyMetadata>
     */
    public function all(): array;
}
