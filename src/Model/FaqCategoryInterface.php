<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * A category of the FAQ: an entry of its own with a title per translation, which FAQ items name in their categories
 * (ADR 0019). See "Categories" in docs/spec/collections.md
 */
interface FaqCategoryInterface extends EntryInterface
{
    /** The code of its content type */
    public const CONTENT_TYPE = 'faq_category';
}
