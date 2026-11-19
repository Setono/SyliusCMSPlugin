<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Collection\CardDisplays;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;

/**
 * The displays the Entries block offers: those the listed content type has cards for, those every picked entry's
 * content type has, or every display a content type has a card for when it can't tell. See "The Entries block" and
 * "Cards" in docs/spec/collections.md
 */
final class CardDisplaysTest extends TestCase
{
    /**
     * @test
     */
    public function it_lists_every_display_a_content_type_has_a_card_for_once_in_the_order_of_the_content_types_and_their_cards(): void
    {
        self::assertSame(['grid', 'carousel', 'accordion'], self::displays()->all());
    }

    /**
     * @test
     */
    public function it_leaves_out_the_displays_of_content_types_that_are_switched_off(): void
    {
        self::assertSame(['grid', 'carousel'], self::displays(disabled: ['faq_item'])->all());
    }

    /**
     * @test
     */
    public function it_offers_the_displays_of_the_listed_content_type(): void
    {
        $displays = self::displays();

        self::assertSame(['grid', 'carousel'], $displays->offeredFor(['blog_post']));
        self::assertSame(['accordion'], $displays->offeredFor(['faq_item']));
        self::assertSame([], $displays->offeredFor(['page']), 'Page has no cards');
    }

    /**
     * @test
     */
    public function it_offers_the_displays_every_one_of_several_content_types_has_in_the_first_s_order(): void
    {
        $displays = self::displays();

        self::assertSame(['grid'], $displays->offeredFor(['blog_post', 'location']));
        self::assertSame(['grid'], $displays->offeredFor(['location', 'blog_post', 'blog_post']));
        self::assertSame([], $displays->offeredFor(['blog_post', 'faq_item']), 'Blog posts and FAQ items share no display');
    }

    /**
     * @test
     */
    public function it_offers_every_display_when_it_cannot_tell_which_content_type_is_listed(): void
    {
        $displays = self::displays();

        self::assertSame(['grid', 'carousel', 'accordion'], $displays->offeredFor(null));
        self::assertSame(['grid', 'carousel', 'accordion'], $displays->offeredFor([]));
        self::assertSame(['grid', 'carousel', 'accordion'], $displays->offeredFor(['removed']), 'A content type the code no longer has tells nothing');
    }

    /**
     * @test
     */
    public function it_finds_the_card_a_content_type_has_for_a_display(): void
    {
        $displays = self::displays();

        self::assertEquals(new Card('carousel', '@SetonoSyliusCMSPlugin/card/blog_post/carousel.html.twig'), $displays->cardOf('blog_post', 'carousel'));
        self::assertEquals(new Card('accordion', '@SetonoSyliusCMSPlugin/card/faq_item/accordion.html.twig'), $displays->cardOf('faq_item', 'accordion'));
        self::assertNull($displays->cardOf('faq_item', 'grid'));
        self::assertNull($displays->cardOf('page', 'grid'));
        self::assertNull($displays->cardOf('removed', 'grid'));
    }

    /**
     * @param list<string> $disabled
     */
    private static function displays(array $disabled = []): CardDisplays
    {
        return new CardDisplays(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
            'location' => ['model' => Location::class, 'translation' => null, 'resource' => 'app.location'],
        ], disabled: $disabled));
    }
}
