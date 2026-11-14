<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A block type and a content type whose code doesn't match ^[a-z][a-z0-9_]*$
 */
#[BlockType(code: 'Invalid-Code', label: 'app.invalid', template: 'invalid.html.twig')]
#[ContentType(code: 'Invalid-Code', label: 'app.invalid')]
class InvalidCode extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
