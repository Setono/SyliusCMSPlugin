<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * One condition of a placement's page scope as the Placements section of its snippet's overview page shows it, in the
 * admin user's locale, such as "Product in taxon", "is one of", ["Shoes", "Include the taxons below them"]
 */
final readonly class PageScopeCondition
{
    /**
     * @param string $kind its kind's code
     * @param string|null $label its kind's label, or null when the code no longer has the kind
     * @param string|null $matches "is one of" or "is not one of", as its kind words it, or null for a kind the code no
     *                             longer has
     * @param list<string> $values what it names: products, taxons and entries by their names, or their codes when they
     *                             no longer exist, a toggle that's on by its label, and other values as they're stored
     */
    public function __construct(
        public string $kind,
        public ?string $label,
        public ?string $matches,
        public array $values,
    ) {
    }
}
