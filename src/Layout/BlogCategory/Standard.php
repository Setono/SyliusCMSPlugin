<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Layout\BlogCategory;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\BlogCategory;

/**
 * Blog category's default layout: the title and the description above the tree, which holds the category's post list.
 * See "Shipped layouts" in docs/spec/layouts-and-presets.md
 */
#[Layout(
    code: 'standard',
    contentType: BlogCategory::class,
    label: 'setono_sylius_cms.layout.blog_category.standard',
    template: '@SetonoSyliusCMSPlugin/layout/blog_category/standard.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/layout/blog_category/standard.svg',
    default: true,
    shows: ['title', 'description'],
)]
final class Standard
{
}
