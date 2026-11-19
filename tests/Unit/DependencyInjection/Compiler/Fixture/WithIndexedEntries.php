<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Field\Entries;

/**
 * A class with an Entries field declared indexed, which only a content type's own shared field may be
 */
final class WithIndexedEntries
{
    /** @var list<string> */
    #[Entries(label: 'app.related', indexed: true)]
    public array $related = [];
}
