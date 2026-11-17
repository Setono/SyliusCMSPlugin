<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The sources the code has, the disabled ones included, which keep resolving the lists they fill
 */
interface SourceRegistryInterface
{
    public function has(string $code): bool;

    /**
     * @throws UnknownSourceException
     */
    public function get(string $code): SourceMetadata;

    /**
     * @return list<SourceMetadata>
     */
    public function all(): array;
}
