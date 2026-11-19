<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Field\Snippet;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type whose own fields hold what blocks hold elsewhere: a list a source may fill, and a snippet
 * it shows
 */
#[ContentType(code: 'lookbook', label: 'app.cms.content_type.lookbook', prefix: 'lookbooks')]
class Lookbook extends Entry
{
    #[Products(label: 'app.cms.field.picks')]
    public mixed $picks = null;

    #[Snippet(label: 'app.cms.field.banner')]
    public ?string $banner = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
