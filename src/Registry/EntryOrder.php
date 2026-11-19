<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Attribute\ContentType;

/**
 * The order a content type's entries are listed in by sources, as #[ContentType(order:)] sets it: by properties of the
 * entry, each ascending or descending, or by the position editors drag entries into in the admin list
 */
final readonly class EntryOrder
{
    /**
     * @param array<string, 'ASC'|'DESC'> $fields property name => direction. By position, it's the position ascending
     * @param bool $byPosition whether editors drag the entries into order in the admin list
     */
    private function __construct(
        public array $fields,
        public bool $byPosition,
    ) {
    }

    /**
     * Newest first, the order of a content type that sets none
     */
    public static function default(): self
    {
        return new self(['createdAt' => 'DESC'], false);
    }

    /**
     * @param mixed $order #[ContentType(order:)]: 'position', or a map from property names to 'ASC' or 'DESC'
     *
     * @throws \InvalidArgumentException for anything else, whose message says what the order must be
     */
    public static function parse(mixed $order): self
    {
        if (ContentType::ORDER_BY_POSITION === $order) {
            return new self(['position' => 'ASC'], true);
        }

        if (!is_array($order) || ([] !== $order && array_is_list($order))) {
            throw new \InvalidArgumentException('The order must be \'position\' or a map of property names to \'ASC\' or \'DESC\'');
        }

        if ([] === $order) {
            throw new \InvalidArgumentException('The order must name at least one property');
        }

        $fields = [];
        foreach ($order as $property => $direction) {
            if ('ASC' !== $direction && 'DESC' !== $direction) {
                throw new \InvalidArgumentException(sprintf('The order orders "%s" by "%s", which must be \'ASC\' or \'DESC\'', $property, is_string($direction) ? $direction : get_debug_type($direction)));
            }

            $fields[(string) $property] = $direction;
        }

        return new self($fields, false);
    }

    /**
     * Newest first, for entries of the model, as Related lists them: this order when it's a date descending, its first
     * property a date of the model's, such as Blog post's publishedAt, and else by createdAt descending. See "Entry
     * sources" in docs/spec/sources.md
     *
     * @param class-string $modelClass
     */
    public function newestFirst(string $modelClass): self
    {
        $first = array_key_first($this->fields);
        if (null !== $first && !$this->byPosition && 'DESC' === $this->fields[$first] && self::isDate($modelClass, $first)) {
            return $this;
        }

        return self::default();
    }

    /**
     * Orders the entries "$alias" of the query by the properties, and then by id, so entries that tie keep one order
     */
    public function applyTo(QueryBuilder $queryBuilder, string $alias): void
    {
        foreach ($this->fields as $property => $direction) {
            $queryBuilder->addOrderBy(sprintf('%s.%s', $alias, $property), $direction);
        }

        $queryBuilder->addOrderBy(sprintf('%s.id', $alias), 'ASC');
    }

    /**
     * Whether the model's property holds a date, by its type
     *
     * @param class-string $modelClass
     */
    private static function isDate(string $modelClass, string $property): bool
    {
        if (!property_exists($modelClass, $property)) {
            return false;
        }

        $type = (new \ReflectionProperty($modelClass, $property))->getType();

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), \DateTimeInterface::class, true);
    }
}
