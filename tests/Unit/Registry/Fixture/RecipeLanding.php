<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;

/**
 * A recipe layout with the code a page layout has too, which layout codes allow, because they're unique only within a content type
 */
#[Layout(code: 'landing', contentType: Recipe::class, label: 'app.recipe_landing', template: 'recipe_landing.html.twig')]
final class RecipeLanding
{
}
