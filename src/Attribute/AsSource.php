<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Registers a source: a service implementing Setono\SyliusCMSPlugin\Source\SourceInterface, which fills list fields of
 * what it lists. See "Sources" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsSource
{
    /**
     * @param string $code stored in each list's "$source"; matches ^[a-z][a-z0-9_]*$ and never changes
     * @param string $label a translation key
     * @param class-string $of what it lists: ProductInterface::class, TaxonInterface::class, EntryInterface::class for
     *                         entries of any content type, or one content type's class. List fields of that kind offer it
     * @param list<string> $needs the context keys it needs, such as "product". It yields nothing where one is missing
     * @param class-string|null $params its parameters class, whose typed properties with field attributes are the
     *                                  parameters editors fill in, or null for none, when it gets a NoParams
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $of,
        public array $needs = [],
        public ?string $params = null,
    ) {
    }
}
