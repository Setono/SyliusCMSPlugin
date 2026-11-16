<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Layout\Page;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * Page's default layout: the title, unless Show title is off, then the tree edge to edge, with no container of its own.
 * See "Shipped layouts" in docs/spec/layouts-and-presets.md
 */
#[Layout(
    code: 'standard',
    contentType: Page::class,
    label: 'setono_sylius_cms.layout.page.standard',
    template: '@SetonoSyliusCMSPlugin/layout/page/standard.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg',
    default: true,
    shows: ['title'],
)]
final class Standard
{
    #[Toggle(label: 'setono_sylius_cms.field.standard.show_title')]
    public bool $showTitle = true;
}
