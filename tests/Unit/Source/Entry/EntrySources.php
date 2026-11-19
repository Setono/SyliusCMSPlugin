<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Entry;

use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;

/**
 * The content types the entry sources' unit tests list: pages, the blog and the FAQ with their categories, and a
 * project's collection without categories, shops
 */
trait EntrySources
{
    /** The model of each content type, by code */
    private const MODELS = [
        'page' => Page::class,
        'blog_post' => BlogPost::class,
        'blog_category' => BlogCategory::class,
        'faq_item' => FaqItem::class,
        'faq_category' => FaqCategory::class,
        'shop' => Shop::class,
    ];

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
            'faq_category' => ['model' => FaqCategory::class, 'translation' => null, 'resource' => 'setono_sylius_cms.faq_category'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
        ]);
    }

    private static function collections(): Collections
    {
        return new Collections(self::contentTypes());
    }

    private static function indexedFields(): IndexedFields
    {
        return new IndexedFields(self::contentTypes(), new ContentTypeFields(new FieldReader()));
    }
}
