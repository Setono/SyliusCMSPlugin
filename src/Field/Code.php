<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * Raw HTML, scripts included, stored as a string and hydrated as ?string. Only the Embed code block uses it, which prints
 * it unescaped in the shop (ADR 0018). See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'code')]
final class Code extends Field
{
}
