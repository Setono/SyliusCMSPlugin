<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryTranslation;

class PostTranslation extends EntryTranslation
{
    /** Typed loosely, so a test can give it a value that isn't text */
    public mixed $excerpt = null;
}
