<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Symfony\Component\Uid\Uuid;

/**
 * A content type's own shared Entries fields declared indexed, whose UUIDs publishing writes to the reference table,
 * and the one of them that holds the entry's categories in a collection with categories: the one whose contentType is
 * the collection's categoryType. The values are read as the entry holds them, so on the rows, its published content,
 * and on a working copy, what the builder edits. See "Categories" in docs/spec/collections.md
 */
final readonly class IndexedFields
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private ContentTypeFields $contentTypeFields,
    ) {
    }

    /**
     * @return array<string, FieldMetadata> by field name, in declaration order
     */
    public function of(ContentTypeMetadata $contentType): array
    {
        return array_filter(
            $this->contentTypeFields->shared($contentType),
            static fn (FieldMetadata $field): bool => $field->field instanceof Entries && $field->field->indexed,
        );
    }

    /**
     * The UUIDs each indexed field of the entry holds, lowercase, each once, in the editor's order. A value that isn't a
     * list, such as a source, holds none, and what isn't a UUID is left out
     *
     * @return array<string, list<string>> by field name
     */
    public function in(EntryInterface $entry): array
    {
        $held = [];
        foreach (array_keys($this->of($this->contentTypes->getByEntry($entry))) as $name) {
            $held[$name] = self::uuids($this->contentTypeFields->read($entry, $name));
        }

        return $held;
    }

    /**
     * The field that holds the categories of an entry of the content type, or null when its collection has none
     */
    public function categoryField(ContentTypeMetadata $contentType): ?FieldMetadata
    {
        $categoryType = $contentType->categoryType;
        if (null === $categoryType) {
            return null;
        }

        foreach ($this->of($contentType) as $field) {
            $named = $field->field instanceof Entries ? $field->field->contentType : null;
            if (null !== $named && $this->isContentType($named, $categoryType)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The UUIDs of the categories the entry is in, lowercase, in the editor's order, or none when its collection has no
     * categories
     *
     * @return list<string>
     */
    public function categoriesOf(EntryInterface $entry): array
    {
        $field = $this->categoryField($this->contentTypes->getByEntry($entry));

        return null === $field ? [] : self::uuids($this->contentTypeFields->read($entry, $field->name));
    }

    private function isContentType(string $class, string $code): bool
    {
        try {
            return $code === $this->contentTypes->getByClass($class)->code;
        } catch (UnknownContentTypeException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private static function uuids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $uuids = [];
        foreach ($value as $item) {
            if (is_string($item) && Uuid::isValid($item)) {
                $uuids[strtolower($item)] = true;
            }
        }

        return array_keys($uuids);
    }
}
