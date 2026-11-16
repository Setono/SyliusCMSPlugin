<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

/**
 * The tree's layout in the stored block tree format. See "The tree" in docs/spec/content-model.md
 */
final class StoredLayout
{
    use StoredValuesTrait;

    /**
     * @param array<string, mixed> $fields field name => value; a translatable field's value is a map of locale code => value
     * @param array<string, mixed> $translated field name => locale code => translation state
     */
    public function __construct(
        public readonly string $code,
        public int $version,
        public array $fields = [],
        public array $translated = [],
    ) {
    }
}
