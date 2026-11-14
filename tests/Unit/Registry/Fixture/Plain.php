<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

#[BlockType(code: 'plain', label: 'app.plain', template: 'plain.html.twig')]
final class Plain
{
}
