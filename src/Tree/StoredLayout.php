<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

/**
 * The tree's layout in the stored block tree format. See "The tree" in docs/spec/content-model.md
 */
final class StoredLayout
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $translated
     */
    public function __construct(
        public readonly string $code,
        public int $version,
        public array $fields = [],
        public array $translated = [],
    ) {
    }
}
