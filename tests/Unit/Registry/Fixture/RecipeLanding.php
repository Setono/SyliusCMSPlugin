<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;

/**
 * A layout of a content type without a URL of its own, which can't have layouts
 */
#[Layout(code: 'landing', contentType: Recipe::class, label: 'app.recipe_landing', template: 'recipe_landing.html.twig', default: true)]
final class RecipeLanding
{
}
