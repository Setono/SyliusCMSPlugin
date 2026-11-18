<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;

/**
 * A collection: all entries of a content type other than the built-in ones, such as the blog's posts, with its
 * categories, the entries of the category content type it declares, when it has some and they aren't switched off.
 * It's named by its entries' content type's code. See "Collection" in GLOSSARY.md
 */
final readonly class CollectionMetadata
{
    public function __construct(
        public ContentTypeMetadata $entries,
        public ?ContentTypeMetadata $categories = null,
    ) {
    }

    public function code(): string
    {
        return $this->entries->code;
    }

    public function hasCategories(): bool
    {
        return null !== $this->categories;
    }
}
