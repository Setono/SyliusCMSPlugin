<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type without a URL, whose tree may hold any block type
 */
#[ContentType(code: 'article', label: 'app.article', blocks: [new Allow('*')])]
class Article extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
