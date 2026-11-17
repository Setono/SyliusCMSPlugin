<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Registry\SourceMetadata;

/**
 * A list field of products or taxons, hydrated as an ItemList. It's hand-picked, stored as an array of codes, or
 * filled by a source of the same kind, stored as {"$source": …, "$params": …, "$limit": …}. Every list field is
 * handled by ListFieldHandler. See "List fields" in docs/spec/sources.md and "Shipped field types" in
 * docs/spec/extending.md
 */
abstract class ListField extends Field
{
    /**
     * @param int|null $max the most items an editor may hand-pick, or null for no most
     * @param bool|list<string> $sources the codes of the sources it offers, true for every source of its kind, or false
     *                                   to allow only hand-picking
     * @param int $limit how many items a new list a source fills shows
     */
    public function __construct(
        string $label,
        public readonly ?int $max = null,
        public readonly bool|array $sources = true,
        public readonly int $limit = 8,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        if (null !== $max && $max < 1) {
            throw new \InvalidArgumentException(sprintf('A %s field must hold at least one item, or have no most, got a most of %d', static::class, $max));
        }

        if ($limit < 1) {
            throw new \InvalidArgumentException(sprintf('A %s field must show at least one item, got a limit of %d', static::class, $limit));
        }

        parent::__construct($label, $help, $required, $translatable);
    }

    /**
     * The type of reference its items are, such as product, which is also the kind the builder searches them by
     */
    abstract public function itemType(): string;

    /**
     * What it lists, such as ProductInterface::class: a source fills it when the source's "of" is this
     *
     * @return class-string
     */
    abstract public function itemClass(): string;

    /**
     * Whether editors may fill it with the source: one that lists what it lists and isn't disabled, and that its sources
     * option allows
     */
    public function offers(SourceMetadata $source): bool
    {
        if ($source->disabled || !$source->lists($this->itemClass())) {
            return false;
        }

        return $this->allows($source->code);
    }

    /**
     * Whether its sources option allows the source, whatever the source lists and whether it's disabled
     */
    public function allows(string $sourceCode): bool
    {
        return is_array($this->sources) ? in_array($sourceCode, $this->sources, true) : $this->sources;
    }

    public function handledBy(): string
    {
        return ListFieldHandler::class;
    }
}
