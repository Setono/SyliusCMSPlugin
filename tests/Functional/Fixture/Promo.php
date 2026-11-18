<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages with a slot of its own, app.promo.after_blocks, which its template renders after the
 * tree with the entry being shown, as a layout's slots get it. SlotsKernel registers it and declares the slot
 */
#[Layout(code: 'promo', contentType: Page::class, label: 'app.cms.layout.promo', template: 'cms/layout/promo.html.twig', slots: ['app.promo.after_blocks'])]
final class Promo
{
}
