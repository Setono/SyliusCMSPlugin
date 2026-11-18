<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * A category of the blog: an entry of its own, with a URL under "blog/category" that's its archive, as its tree holds
 * the list of its posts (ADR 0019). See "Categories" in docs/spec/collections.md
 *
 * @method BlogCategoryTranslationInterface getTranslation(?string $locale = null)
 */
interface BlogCategoryInterface extends EntryInterface
{
    /** The code of its content type */
    public const CONTENT_TYPE = 'blog_category';
}
