<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A snippet, picked among the snippets whose declared context the entry provides. Stored as the snippet's UUID
 * (ADR 0002), a dependency an export brings along, and hydrated as ?SnippetInterface: the snippet while the shopper may
 * see it. See "Shipped field types" in docs/spec/extending.md and "Snippet references" in docs/spec/builder.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'snippet')]
final class Snippet extends Field
{
}
