<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A layout as the registry knows it: what upgrading its stored data needs. The rest of its #[Layout] options come
 * with the layout machinery (#236)
 */
final readonly class LayoutMetadata
{
    /**
     * @param string $contentType the code of the content type it belongs to
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     */
    public function __construct(
        public string $code,
        public string $contentType,
        public int $version,
        public string $class,
        public array $fields,
    ) {
    }
}
