<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A row of the reference table: one UUID that an Entries field declared indexed holds in an entry's published content,
 * such as one of a blog post's categories. Listings and sources filter on it with one indexed join, so a category filter
 * pages in SQL on MySQL and PostgreSQL. It mirrors published content only, and publishing writes it. See "Categories" in
 * docs/spec/collections.md and ADR 0019
 */
interface EntryReferenceInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    /**
     * The name of the entry's field that holds it, such as categories
     */
    public function getField(): string;

    public function setField(string $field): void;

    /**
     * The UUID of the entry it refers to, lowercase, as content holds it. Content refers to entries by UUID (ADR 0002),
     * so a row can name one that was deleted
     */
    public function getUuid(): string;

    public function setUuid(string $uuid): void;
}
