<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A content type with a card for a display that isn't a code, which the Entries block's class names can't hold
 */
#[ContentType(code: 'carded_oddly', label: 'app.carded_oddly', cards: [new Card('Big grid', 'cms/card/carded_oddly/big_grid.html.twig')])]
class CardedOddly extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
