<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A product, picked by searching the catalog. Stored as the product's code (ADR 0002), and hydrated as
 * ?ProductInterface: the product while the shopper may see it. See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'product')]
final class Product extends Field
{
}
