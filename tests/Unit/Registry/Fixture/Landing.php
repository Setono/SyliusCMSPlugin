<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages
 */
#[Layout(code: 'landing', contentType: Page::class, label: 'app.landing', template: 'landing.html.twig', version: 2)]
final class Landing
{
    #[Toggle(label: 'app.transparent_header')]
    public bool $transparentHeader = false;
}
