<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Where a problem is fixed: a link to an admin page, such as the builder at the block. The admin turns it into a URL
 * with the route and its parameters, so a problem kind needs no router
 */
final readonly class Fix
{
    /**
     * @param string $label a translation key, such as "Open in builder"
     * @param array<string, string> $parameters the route's parameters, which may hold `_fragment`
     */
    public function __construct(
        public string $label,
        public string $route,
        public array $parameters = [],
    ) {
    }

    /**
     * Opens the entry in the builder, at the block when it's given, editing the locale when it's given
     */
    public static function inBuilder(EntryInterface $entry, ?string $block = null, ?string $locale = null): self
    {
        return new self('setono_sylius_cms.ui.open_in_builder', 'setono_sylius_cms_admin_builder', array_filter(
            ['uuid' => $entry->getUuid(), 'block' => $block, 'locale' => $locale],
            static fn (?string $parameter): bool => null !== $parameter,
        ));
    }
}
