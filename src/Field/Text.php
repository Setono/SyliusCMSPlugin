<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A single line of text, stored as a string and hydrated as ?string
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'text', translatable: true)]
final class Text extends Field
{
    public function __construct(
        string $label,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
        public readonly ?int $maxLength = null,
    ) {
        parent::__construct($label, $help, $required, $translatable);
    }
}
