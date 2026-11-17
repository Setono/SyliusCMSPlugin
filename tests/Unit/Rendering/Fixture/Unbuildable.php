<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * A block type that throws when it's hydrated, before any template renders
 */
#[BlockType(code: 'unbuildable', label: 'app.block_type.unbuildable', template: 'unbuildable.html.twig')]
final class Unbuildable
{
    public const MESSAGE = 'An unbuildable block can\'t be built';

    public function __construct()
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
