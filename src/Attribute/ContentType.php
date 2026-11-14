<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Adds the CMS metadata to a content type: a Doctrine entity that extends Entry and is registered as a Sylius resource.
 * See "Content types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentType
{
    /**
     * @param string $code the discriminator value and the column prefix; never changes
     * @param string $label a translation key
     * @param string|null $prefix the URL prefix: null means no URL of its own, and '' means URLs at the root
     * @param list<Allow>|null $blocks what the top level of its tree may hold: null means it has no block tree
     */
    public function __construct(
        public string $code,
        public string $label,
        public int $version = 1,
        public ?string $prefix = null,
        public ?array $blocks = null,
    ) {
    }
}
