<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type that names the recipes as its categories, but whose Entries field of recipes isn't indexed,
 * so listings couldn't filter on them
 */
#[ContentType(code: 'workshop', label: 'app.workshop', categoryType: Recipe::class)]
class Workshop extends Entry
{
    /** @var list<string>|null */
    #[Entries(label: 'app.cms.field.recipes', contentType: Recipe::class, sources: false)]
    public ?array $recipes = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
