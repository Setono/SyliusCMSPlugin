<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages whose template renders a slot of its own, app.promo.after_blocks
 */
#[Layout(code: 'promo', contentType: Page::class, label: 'Promo', template: 'promo.html.twig', slots: ['app.promo.after_blocks'])]
final class Promo
{
}
