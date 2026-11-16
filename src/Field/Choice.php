<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * One value, or several when multiple, out of a fixed set, or out of the set a choices provider gives. Stored as the
 * chosen value as a string, or an array of strings when multiple, and hydrated as ?string or list<string>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'choice')]
final class Choice extends Field
{
    /**
     * @param array<array-key, string> $choices value => label (a translation key). PHP turns numeric values into int keys
     * @param class-string<ChoicesProviderInterface>|null $choicesProvider the service whose choices are used instead of
     *                                                                     $choices, for choices that come from the store
     */
    public function __construct(
        string $label,
        public readonly array $choices = [],
        public readonly bool $multiple = false,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
        public readonly ?string $choicesProvider = null,
    ) {
        parent::__construct($label, $help, $required, $translatable);
    }

    /**
     * The values of the fixed choices
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(strval(...), array_keys($this->choices));
    }
}
