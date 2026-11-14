<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A second page layout with Landing's code
 */
#[Layout(code: 'landing', contentType: Page::class, label: 'app.second_landing', template: 'second_landing.html.twig')]
final class SecondLanding
{
}
