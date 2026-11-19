<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\CardDisplayChoicesProvider;
use Setono\SyliusCMSPlugin\Collection\CardDisplays;
use Setono\SyliusCMSPlugin\Collection\CollectionNames;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\CardedOddly;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\BlockTreeValidatorTest;
use Symfony\Component\Translation\Loader\ArrayLoader;

/**
 * What the Entries block's display is picked from: every display a content type has a card for, named by its
 * translation, setono_sylius_cms.choice.entries_display.<display>. See "The Entries block" in docs/spec/collections.md
 */
final class CardDisplayChoicesProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_offers_every_display_a_content_type_has_a_card_for_named_by_its_translation(): void
    {
        $translator = BlockTreeValidatorTest::translator();
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['setono_sylius_cms.choice.entries_display.grid' => 'Gitter'], 'da');

        $provider = new CardDisplayChoicesProvider(new CardDisplays(new ContentTypeRegistry([
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
        ])), new CollectionNames($translator));

        self::assertSame(['grid' => 'Grid', 'carousel' => 'Carousel', 'accordion' => 'Accordion'], $provider->getChoices('en_US'));
        self::assertSame('Gitter', $provider->getChoices('da_DK')['grid'] ?? null);
    }

    /**
     * A project's own display without a translation is named by its code
     *
     * @test
     */
    public function it_names_a_display_without_a_translation_by_its_code(): void
    {
        $provider = new CardDisplayChoicesProvider(new CardDisplays(new ContentTypeRegistry([
            'carded_oddly' => ['model' => CardedOddly::class, 'translation' => null, 'resource' => 'app.carded_oddly'],
        ])), new CollectionNames(BlockTreeValidatorTest::translator()));

        self::assertSame(['Big grid' => 'Big grid'], $provider->getChoices('en_US'));
    }
}
