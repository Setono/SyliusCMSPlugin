<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Entries, hand-picked by UUID in the order editors put them. Hydrated as ItemList<EntryInterface> of the entries the
 * shopper may see, of its content type when it names one. The entries are links, not dependencies: an export leaves them
 * as UUIDs. A content type's own field declared indexed has its UUIDs written to the reference table on publish, which
 * is how a collection's entries name their categories. A field may instead list the categories of the collection
 * another property of the same object holds, as From a collection's categories parameter does. See "Shipped field
 * types" and "Sources" in docs/spec/extending.md and "Categories" in docs/spec/collections.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'entries')]
final class Entries extends ListField
{
    /**
     * @param class-string|null $contentType the content type its entries are of, by its model or a class or interface
     *                                       the model extends, such as BlogCategory::class, or null for any
     * @param string|null $categoriesOf the property of the same object that holds the code of a collection's content
     *                                  type, whose categories it lists, such as contentType. The builder's picker
     *                                  searches them, and clears the field when that property changes
     * @param bool $indexed whether publishing writes its UUIDs to the (entry, field, UUID) reference table, so listings
     *                      filter on it in SQL. Only a content type's own shared field is indexed
     * @param bool|list<string> $sources
     */
    public function __construct(
        string $label,
        public readonly ?string $contentType = null,
        public readonly ?string $categoriesOf = null,
        public readonly bool $indexed = false,
        ?int $max = null,
        bool|array $sources = true,
        int $limit = 8,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        parent::__construct($label, $max, $sources, $limit, $help, $required, $translatable);
    }

    public function itemType(): string
    {
        return ReferenceType::ENTRY;
    }

    public function itemClass(): string
    {
        return EntryInterface::class;
    }

    /**
     * An entry, of its content type when it names one
     */
    public function accepts(mixed $item): bool
    {
        return $item instanceof EntryInterface && (null === $this->contentType || $item instanceof $this->contentType);
    }

    public function handledBy(): string
    {
        return EntriesHandler::class;
    }
}
