<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type with its own URL, whose meta description falls back to its excerpt, as a blog post's does
 */
#[ContentType(code: 'post', label: 'app.post', prefix: 'posts', descriptionField: 'excerpt')]
class Post extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new PostTranslation();
    }
}
