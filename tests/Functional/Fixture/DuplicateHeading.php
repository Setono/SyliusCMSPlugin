<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * A project's block type that takes the code of the shipped Heading
 */
#[BlockType(code: 'heading', label: 'app.cms.block_type.heading', template: 'cms/block/heading.html.twig')]
final class DuplicateHeading
{
}
