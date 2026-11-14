<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

interface BlockTypeRegistryInterface
{
    public function has(string $code): bool;

    /**
     * @throws UnknownBlockTypeException
     */
    public function get(string $code): BlockTypeMetadata;

    /**
     * @param class-string $class
     *
     * @throws UnknownBlockTypeException
     */
    public function getByClass(string $class): BlockTypeMetadata;

    /**
     * @return list<BlockTypeMetadata>
     */
    public function all(): array;
}
