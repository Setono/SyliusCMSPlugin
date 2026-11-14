<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * A block, a layout or an entry saved with a newer version of its block type, layout or content type than the code
 * has, such as after a deploy is rolled back. Preset inserts and imports refuse it
 */
final readonly class NewerVersion
{
    public function __construct(
        public Kind $kind,
        public string $code,
        public int $version,
        public int $codeVersion,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('The %s "%s" was saved with version %d, but the code has version %d', str_replace('_', ' ', $this->kind->value), $this->code, $this->version, $this->codeVersion);
    }
}
