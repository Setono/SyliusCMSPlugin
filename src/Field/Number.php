<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A number, stored as a JSON number and hydrated as int|float|null, or as ?int when it takes only whole numbers
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'number')]
final class Number extends Field
{
    /**
     * @param int|float|null $min the lowest value, or null for none
     * @param int|float|null $max the highest value, or null for none
     * @param int|float|null $step the steps the value moves in, counted from min (or 0 without one), as in an HTML
     *                             number input, or null for any value
     * @param bool $integer whether it takes only whole numbers
     */
    public function __construct(
        string $label,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly int|float|null $step = null,
        public readonly bool $integer = false,
    ) {
        if (null !== $step && $step <= 0) {
            throw new \InvalidArgumentException(sprintf('A number field moves in steps above 0, got a step of %s', $step));
        }

        if (null !== $min && null !== $max && $min > $max) {
            throw new \InvalidArgumentException(sprintf('A number field\'s lowest value can\'t be above its highest, got %s and %s', $min, $max));
        }

        parent::__construct($label, $help, $required, $translatable);
    }
}
