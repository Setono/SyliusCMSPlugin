<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * The collections the admin lists, each with its categories: every content type that isn't switched off and isn't a
 * built-in one, Page or Snippet, nor another's category content type, which is reached through its collection. The
 * shipped ones, such as the blog, come first, then a project's own, each in the order it's registered. See "Navigation"
 * and "Entry lists" in docs/spec/admin.md
 */
final readonly class Collections
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
    }

    /**
     * @return list<CollectionMetadata>
     */
    public function all(): array
    {
        $collections = [];
        $categoryTypes = [];
        foreach ($this->contentTypes->enabled() as $contentType) {
            if ($contentType->isBuiltIn()) {
                continue;
            }

            $categories = $this->categoriesOf($contentType);
            if (null !== $categories) {
                $categoryTypes[] = $categories->code;
            }

            $collections[] = new CollectionMetadata($contentType, $categories);
        }

        $collections = array_filter($collections, static fn (CollectionMetadata $collection): bool => !in_array($collection->code(), $categoryTypes, true));

        return [
            ...array_filter($collections, static fn (CollectionMetadata $collection): bool => $collection->entries->isShipped()),
            ...array_filter($collections, static fn (CollectionMetadata $collection): bool => !$collection->entries->isShipped()),
        ];
    }

    /**
     * The collection whose entries or whose categories are of the content type, or null for a built-in or disabled one
     */
    public function of(string $contentType): ?CollectionMetadata
    {
        foreach ($this->all() as $collection) {
            if ($contentType === $collection->code() || $contentType === $collection->categories?->code) {
                return $collection;
            }
        }

        return null;
    }

    /**
     * Whether the code is that of a collection's entries whose collection has categories, such as blog_post, which lists
     * its entries and its categories together
     */
    public function hasCategories(string $contentType): bool
    {
        $collection = $this->of($contentType);

        return null !== $collection && $contentType === $collection->code() && $collection->hasCategories();
    }

    /**
     * The category content type the content type declares, while it's a content type that isn't switched off
     */
    private function categoriesOf(ContentTypeMetadata $contentType): ?ContentTypeMetadata
    {
        if (null === $contentType->categoryType) {
            return null;
        }

        $categories = $this->contentTypes->get($contentType->categoryType);

        return $categories->disabled ? null : $categories;
    }
}
