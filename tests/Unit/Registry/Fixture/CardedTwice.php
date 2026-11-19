<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A content type with two cards for the grid display
 */
#[ContentType(code: 'carded_twice', label: 'app.carded_twice', cards: [
    new Card('grid', 'cms/card/carded_twice/grid.html.twig'),
    new Card('grid', 'cms/card/carded_twice/other_grid.html.twig'),
])]
class CardedTwice extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
