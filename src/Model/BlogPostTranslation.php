<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Field\Textarea;

/**
 * A blog post's translation, with its excerpt in the column blog_post_excerpt
 */
class BlogPostTranslation extends EntryTranslation implements BlogPostTranslationInterface
{
    #[Textarea(label: 'setono_sylius_cms.field.blog_post.excerpt')]
    protected ?string $excerpt = null;

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }
}
