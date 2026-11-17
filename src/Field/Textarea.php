<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * Text over several lines, stored as a string with its newlines kept and hydrated as ?string
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'textarea', translatable: true, bindable: [BindingType::Text, BindingType::Number])]
final class Textarea extends Field
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

    /**
     * A textarea's value is a string with an optional max length, just like a text field's, so the Text handler handles it
     */
    public function handledBy(): string
    {
        return TextHandler::class;
    }
}
