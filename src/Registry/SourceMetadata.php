<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * A source as its #[AsSource] declares it, with the fields of its parameters class
 */
final readonly class SourceMetadata
{
    /**
     * @param string $label a translation key
     * @param class-string $of what it lists
     * @param list<string> $needs the context keys it needs
     * @param class-string|null $params its parameters class, or null when it has none
     * @param array<string, FieldMetadata> $fields the parameters editors fill in, by property name
     * @param bool $disabled switched off in setono_sylius_cms.disabled.sources: editors aren't offered it, but the lists
     *                       it fills already keep resolving through it
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $of,
        public array $needs,
        public ?string $params,
        public array $fields,
        public SourceInterface $source,
        public bool $disabled = false,
    ) {
    }

    /**
     * Whether it lists what a list field of $class lists, such as products for a Products field
     *
     * @param class-string $class
     */
    public function lists(string $class): bool
    {
        return is_a($this->of, $class, true);
    }
}
