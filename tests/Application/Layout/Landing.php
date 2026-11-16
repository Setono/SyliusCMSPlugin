<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Layout;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * The test application's own layout for pages, as a project adds one: a hero with a tagline instead of the title, which
 * it doesn't print, and a header that can be transparent
 */
#[Layout(
    code: 'landing',
    contentType: Page::class,
    label: 'app.cms.layout.landing',
    template: 'cms/layout/landing.html.twig',
)]
final class Landing
{
    #[Text(label: 'app.cms.field.tagline')]
    public ?string $tagline = null;

    #[Toggle(label: 'app.cms.field.transparent_header')]
    public bool $transparentHeader = false;
}
