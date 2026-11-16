<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

#[BlockType(code: 'slide', label: 'app.slide', template: 'slide.html.twig', parents: [Gallery::class])]
final class Slide
{
}
