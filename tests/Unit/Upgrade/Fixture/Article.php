<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type at version 2, with its own URL and a block tree
 */
#[ContentType(code: 'article', label: 'app.article', version: 2, prefix: 'articles', blocks: [new Allow('*')])]
class Article extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
