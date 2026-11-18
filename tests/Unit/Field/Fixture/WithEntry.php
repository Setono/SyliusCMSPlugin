<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Entry;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

final class WithEntry
{
    #[Entry(label: 'app.category', contentType: BlogCategory::class)]
    public ?EntryInterface $category = null;
}
