<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A second layout for pages that is marked as their default too
 */
#[Layout(code: 'wide', contentType: Page::class, label: 'app.wide', template: 'wide.html.twig', default: true)]
final class Wide
{
}
