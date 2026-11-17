<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * Formatted text, stored as a ProseMirror document and hydrated as ?Setono\SyliusCMSPlugin\Value\RichText.
 * See "Rich text" in docs/spec/content-model.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'rich_text', translatable: true, bindable: [BindingType::Text, BindingType::Number])]
final class RichText extends Field
{
}
