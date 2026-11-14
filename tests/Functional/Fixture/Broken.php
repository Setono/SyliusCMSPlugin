<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * A project's block type whose template always throws. Only BrokenBlockKernel registers it
 */
#[BlockType(code: 'broken', label: 'Broken', template: 'cms/block/broken.html.twig')]
final class Broken
{
}
