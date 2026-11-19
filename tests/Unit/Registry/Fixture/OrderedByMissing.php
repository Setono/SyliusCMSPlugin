<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A content type ordered by a property its entries don't have
 */
#[ContentType(code: 'ordered_by_missing', label: 'app.ordered_by_missing', order: ['missing' => 'ASC'])]
class OrderedByMissing extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
