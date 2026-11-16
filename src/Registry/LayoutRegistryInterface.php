<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The layouts of each content type. A layout's code is unique only within its content type
 */
interface LayoutRegistryInterface
{
    public function has(string $contentType, string $code): bool;

    /**
     * @throws UnknownLayoutException
     */
    public function get(string $contentType, string $code): LayoutMetadata;
}
