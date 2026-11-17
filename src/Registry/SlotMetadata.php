<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A slot as the code declares it under setono_sylius_cms.slots: its own identifier, never a template event's name
 * (ADR 0004), its label, the context keys it provides, its cap, and the template event it renders in, if it's tied to
 * one. See "Slots" in docs/spec/slots.md
 */
final readonly class SlotMetadata
{
    /** Codes joined by dots, such as product.after_price */
    public const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * @param string $label a translation key
     * @param list<string> $provides the context keys it provides
     * @param int|null $cap the number of placements that show at most, or null for no cap
     * @param string|null $templateEvent the Sylius template event it renders in, or null for a slot rendered with cms_slot()
     * @param int $templateEventPriority the priority of its block on the template event
     */
    public function __construct(
        public string $identifier,
        public string $label,
        public array $provides = [],
        public ?int $cap = null,
        public ?string $templateEvent = null,
        public int $templateEventPriority = 0,
    ) {
    }

    public static function isIdentifier(string $identifier): bool
    {
        return 1 === preg_match(self::IDENTIFIER_PATTERN, $identifier);
    }

    /**
     * The classes of the wrapper a slot with something to show prints: cms-slot, and a modifier of its identifier with
     * its dots and underscores turned into hyphens, such as cms-slot--product-after-price
     */
    public function wrapperClass(): string
    {
        return 'cms-slot cms-slot--' . strtr($this->identifier, '._', '--');
    }

    /**
     * Whether it provides every one of these context keys, which a snippet that needs them must find where it's placed
     *
     * @param list<string> $keys
     */
    public function providesAll(array $keys): bool
    {
        return [] === array_diff($keys, $this->provides);
    }
}
