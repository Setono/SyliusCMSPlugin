<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A default layout for pages, with everything #[Layout] takes
 */
#[Layout(
    code: 'standard',
    contentType: Page::class,
    label: 'setono_sylius_cms.layout.page.standard',
    template: 'standard.html.twig',
    thumbnail: '/build/app/standard.svg',
    default: true,
    shows: ['title'],
    slots: ['app.standard.after_title'],
)]
final class Standard
{
    #[Toggle(label: 'app.show_title')]
    public bool $showTitle = true;
}
