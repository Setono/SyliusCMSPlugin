<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A layout for pages with a translatable field and a shared one
 */
#[Layout(code: 'promo', contentType: Page::class, label: 'app.promo', template: 'promo.html.twig')]
final class Promo
{
    #[Text(label: 'app.tagline')]
    public ?string $tagline = null;

    #[Toggle(label: 'app.dark')]
    public bool $dark = false;
}
