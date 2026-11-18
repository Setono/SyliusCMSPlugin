<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * A post of the blog, the shipped collection of blog posts in their Article layout, each in any of the blog's
 * categories. See "Shipped content types" in docs/spec/collections.md
 *
 * @method BlogPostTranslationInterface getTranslation(?string $locale = null)
 */
interface BlogPostInterface extends FillsOnPublishInterface
{
    /** The code of its content type */
    public const CONTENT_TYPE = 'blog_post';

    /**
     * Its date, in UTC, which posts are listed newest first by. Its first publish fills it when it's empty, and later
     * publishes keep it, so editors can change it, such as to backdate a post. It isn't "Show from", which decides only
     * when published content shows. See "The blog post's date" in docs/spec/collections.md
     */
    public function getPublishedAt(): ?\DateTimeImmutable;

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void;

    /**
     * The UUID of its featured image, a media item
     */
    public function getFeaturedImage(): ?string;

    public function setFeaturedImage(?string $featuredImage): void;

    /**
     * The UUIDs of the blog categories it's in, in the editor's order. Publishing writes them to the reference table
     *
     * @return list<string>
     */
    public function getCategories(): array;

    /**
     * @param list<string> $categories blog category UUIDs
     */
    public function setCategories(array $categories): void;
}
