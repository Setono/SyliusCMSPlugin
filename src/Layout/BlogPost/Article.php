<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Layout\BlogPost;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\BlogPost;

/**
 * Blog post's default layout, at a contained width: the title, the date, links to the post's categories and the
 * featured image above the tree. See "Shipped layouts" in docs/spec/layouts-and-presets.md
 */
#[Layout(
    code: 'article',
    contentType: BlogPost::class,
    label: 'setono_sylius_cms.layout.blog_post.article',
    template: '@SetonoSyliusCMSPlugin/layout/blog_post/article.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/layout/blog_post/article.svg',
    default: true,
    shows: ['title', 'publishedAt', 'categories', 'featuredImage'],
)]
final class Article
{
}
