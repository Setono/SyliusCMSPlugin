<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * An item of the FAQ, the shipped collection of questions and their answers: its title is the question, and its
 * translations hold the answer. It has no URL of its own, and is listed by an Entries block. See "Shipped content
 * types" in docs/spec/collections.md
 *
 * @method FaqItemTranslationInterface getTranslation(?string $locale = null)
 */
interface FaqItemInterface extends EntryInterface
{
    /** The code of its content type */
    public const CONTENT_TYPE = 'faq_item';

    /**
     * The UUIDs of the FAQ categories it's in, in the editor's order. Publishing writes them to the reference table
     *
     * @return list<string>
     */
    public function getCategories(): array;

    /**
     * @param list<string> $categories FAQ category UUIDs
     */
    public function setCategories(array $categories): void;
}
