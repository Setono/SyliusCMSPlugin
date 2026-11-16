<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's Page layout marked as the default, beside the shipped Standard layout that is the default already
 */
#[Layout(code: 'also_default', contentType: Page::class, label: 'app.cms.layout.landing', template: 'cms/layout/landing.html.twig', default: true)]
final class AlsoDefault
{
}
