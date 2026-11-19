<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type with every #[ContentType] option set
 */
#[ContentType(
    code: 'location',
    label: 'app.cms.content_type.location',
    version: 2,
    prefix: 'stores',
    blocks: [new Allow('*')],
    cards: [new Card('grid', 'cms/card/location/grid.html.twig')],
    order: ['city' => 'ASC'],
    categoryType: Recipe::class,
    descriptionField: 'title',
)]
class Location extends Entry
{
    public ?string $city = null;

    /**
     * The recipes it's in, its categories
     *
     * @var list<string>|null
     */
    #[Entries(label: 'app.cms.field.recipes', contentType: Recipe::class, indexed: true, sources: false)]
    public ?array $recipes = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
