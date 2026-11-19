<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\EntryContextAwareInterface;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * A project's entry source that fits only a blog category as the host page's entry
 */
#[AsSource(code: 'category_posts', label: 'app.source.category_posts', of: EntryInterface::class, needs: ['entry'])]
final class CategoryPosts implements SourceInterface, EntryContextAwareInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }

    public function supportsEntryContentType(string $code): bool
    {
        return 'blog_category' === $code;
    }
}
