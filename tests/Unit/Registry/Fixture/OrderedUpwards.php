<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A content type ordered in a direction that's neither ASC nor DESC
 */
#[ContentType(code: 'ordered_upwards', label: 'app.ordered_upwards', order: ['createdAt' => 'UP'])]
class OrderedUpwards extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
