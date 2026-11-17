<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture;

final readonly class Recipe
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {
    }
}
