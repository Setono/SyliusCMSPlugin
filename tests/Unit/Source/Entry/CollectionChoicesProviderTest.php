<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Entry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\CollectionNames;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Source\Entry\CollectionChoicesProvider;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\BlockTreeValidatorTest;
use Symfony\Component\Translation\Loader\ArrayLoader;

/**
 * What From a collection's contentType parameter is picked from: the collections, by the code of their entries'
 * content type, named as the hub names them. See "Entry sources" in docs/spec/sources.md
 */
final class CollectionChoicesProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_offers_the_collections_by_the_code_of_their_entries_content_type_named_as_the_hub_names_them(): void
    {
        $translator = BlockTreeValidatorTest::translator();
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['app.shop' => 'Shop', 'setono_sylius_cms.content_type_plural.shop' => 'Shops'], 'en');
        $translator->addResource('array', ['setono_sylius_cms.collection.blog_post' => 'Blogg'], 'da');

        $provider = new CollectionChoicesProvider(new Collections(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
            'faq_category' => ['model' => FaqCategory::class, 'translation' => null, 'resource' => 'setono_sylius_cms.faq_category'],
        ], disabled: ['recipe'])), new CollectionNames($translator));

        // The shipped collections first, by their names, then a project's by its plural; categories aren't collections
        self::assertSame(['blog_post' => 'Blog', 'faq_item' => 'FAQ', 'shop' => 'Shops'], $provider->getChoices('en_US'));
        self::assertSame('Blogg', $provider->getChoices('da_DK')['blog_post'] ?? null);
    }
}
