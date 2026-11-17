<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Uid\Uuid;

/**
 * How a Snippet field refers to its snippet: as an entry, by its UUID, that's a dependency an export brings along. An
 * entry a link or an Entry field refers to isn't one, so a reference to an entry that's a dependency is a reference to a
 * snippet whose tree renders inside the tree that holds it. See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final class SnippetReferences
{
    public const TYPE = ReferenceType::ENTRY;

    private function __construct()
    {
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    public static function isUuid(mixed $value): bool
    {
        return is_string($value) && Uuid::isValid($value);
    }

    /**
     * The reference to the snippet, by its UUID in the lower case it's stored and loaded in
     */
    public static function reference(string $uuid): Reference
    {
        return new Reference(self::TYPE, strtolower($uuid), true);
    }

    /**
     * Whether the reference is a Snippet field's: to an entry that's a dependency
     */
    public static function isSnippetReference(Reference $reference): bool
    {
        return self::TYPE === $reference->type && $reference->dependency;
    }
}
