<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * A project's block type that throws when it's hydrated, before its template renders, as a field handler that throws
 * would. Only BrokenBlockKernel registers it
 */
#[BlockType(code: 'unbuildable', label: 'Unbuildable', template: 'cms/block/broken.html.twig')]
final class Unbuildable
{
    public const MESSAGE = 'An unbuildable block can\'t be built';

    public function __construct()
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
