<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A layout as the registry knows it: its #[Layout] options and its fields
 */
final readonly class LayoutMetadata
{
    /**
     * @param string $contentType the code of the content type it belongs to
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     * @param string $label a translation key
     * @param string|null $thumbnail a public path to the wireframe card in the create dialog and the entry settings
     * @param list<string> $shows the entry fields its template prints, by property name
     * @param bool $disabled switched off in setono_sylius_cms.disabled.layouts: editors can't pick it, and its
     *                       entries render in the default layout
     * @param list<string> $slots the slots its template renders, by identifier
     */
    public function __construct(
        public string $code,
        public string $contentType,
        public int $version,
        public string $class,
        public array $fields,
        public string $label,
        public string $template,
        public ?string $thumbnail,
        public array $shows,
        public bool $disabled,
        public array $slots = [],
    ) {
    }

    /**
     * Whether its template renders the slot, so an entry in it hosts the slot's placements
     */
    public function renders(string $slot): bool
    {
        return in_array($slot, $this->slots, true);
    }
}
