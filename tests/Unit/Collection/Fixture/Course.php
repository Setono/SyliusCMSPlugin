<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

/**
 * A project's collection in the blog's categories, with an indexed Entries field before its categories that names a
 * class no content type of the registry is
 */
#[ContentType(code: 'course', label: 'app.course', categoryType: BlogCategory::class)]
class Course extends Entry
{
    /** @var list<string>|null */
    #[Entries(label: 'app.cms.field.recipes', contentType: Recipe::class, indexed: true, sources: false)]
    public ?array $recipes = null;

    /** @var list<string>|null */
    #[Entries(label: 'app.cms.field.categories', contentType: BlogCategory::class, indexed: true, sources: false)]
    public ?array $categories = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
