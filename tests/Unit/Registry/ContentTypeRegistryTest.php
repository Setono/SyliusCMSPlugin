<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\OutletLocation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SeasonalRecipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Untagged;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\PostTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;

final class ContentTypeRegistryTest extends TestCase
{
    private const PAGE = ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'];

    /**
     * @test
     */
    public function it_reads_a_content_type_from_its_attribute_and_its_resource(): void
    {
        $page = (new ContentTypeRegistry(['page' => self::PAGE]))->get('page');

        self::assertSame('page', $page->code);
        self::assertSame('setono_sylius_cms.content_type.page', $page->label);
        self::assertSame(1, $page->version);
        self::assertSame('', $page->prefix);
        self::assertEquals([new Allow('*')], $page->blocks);
        self::assertSame(Page::class, $page->modelClass);
        self::assertSame(PageTranslation::class, $page->translationModelClass);
        self::assertSame('setono_sylius_cms.page', $page->resourceAlias);
        self::assertTrue($page->hasOwnUrl());
        self::assertFalse($page->isSnippet());
        self::assertNull($page->descriptionField, 'A page\'s meta description falls back to nothing');
    }

    /**
     * @test
     */
    public function it_reads_the_cards_the_order_and_the_category_type_of_a_content_type_from_the_nearest_class_that_has_the_attribute(): void
    {
        $location = (new ContentTypeRegistry(['location' => ['model' => OutletLocation::class, 'translation' => null, 'resource' => 'app.location'], 'recipe' => ['model' => SeasonalRecipe::class, 'translation' => null, 'resource' => 'app.recipe']]))->get('location');

        self::assertSame('location', $location->code);
        self::assertSame(2, $location->version);
        self::assertEquals([new Card('grid', 'cms/card/location/grid.html.twig')], $location->cards);
        self::assertSame(['city' => 'ASC'], $location->order->fields);
        self::assertSame('recipe', $location->categoryType, 'By its code, resolved once');
    }

    /**
     * @test
     */
    public function it_orders_the_entries_of_a_content_type_that_sets_no_order_newest_first(): void
    {
        $page = (new ContentTypeRegistry(['page' => self::PAGE]))->get('page');

        self::assertSame(['createdAt' => 'DESC'], $page->order->fields);
        self::assertFalse($page->order->byPosition);
        self::assertSame([], $page->cards);
        self::assertNull($page->categoryType);
    }

    /**
     * @test
     */
    public function it_tells_the_built_in_and_the_shipped_content_types_from_a_projects_own(): void
    {
        $registry = new ContentTypeRegistry([
            'page' => self::PAGE,
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
            'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'setono_sylius_cms.post'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);

        self::assertTrue($registry->get('page')->isBuiltIn());
        self::assertTrue($registry->get('snippet')->isBuiltIn());
        self::assertFalse($registry->get('post')->isBuiltIn());
        self::assertFalse($registry->get('recipe')->isBuiltIn());

        self::assertTrue($registry->get('post')->isShipped(), 'A content type of one of the plugin\'s own resources ships with it');
        self::assertFalse($registry->get('recipe')->isShipped());
    }

    /**
     * A disabled content type's entries are still read, but editors and shoppers don't see it
     *
     * @test
     */
    public function it_keeps_a_disabled_content_type_but_lists_only_the_enabled_ones_as_enabled(): void
    {
        $registry = new ContentTypeRegistry([
            'page' => self::PAGE,
            'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'setono_sylius_cms.post'],
        ], disabled: ['post']);

        self::assertTrue($registry->has('post'));
        self::assertTrue($registry->get('post')->disabled);
        self::assertSame('post', $registry->getByEntry(new Post())->code);
        self::assertSame(['page', 'post'], array_map(static fn ($contentType) => $contentType->code, $registry->all()));

        self::assertFalse($registry->get('page')->disabled);
        self::assertSame(['page'], array_map(static fn ($contentType) => $contentType->code, $registry->enabled()));
        self::assertTrue($registry->isEnabled('page'));
        self::assertFalse($registry->isEnabled('post'));
        self::assertFalse($registry->isEnabled('recipe'), 'A code no content type has is not enabled');
        self::assertSame(['post'], $registry->disabledCodes());
    }

    /**
     * @test
     */
    public function it_tells_the_codes_of_the_content_types_switched_off_it_has(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE], disabled: ['post']);

        self::assertSame([], $registry->disabledCodes(), 'A code no content type has is left out');
    }

    /**
     * A snippet has no URL of its own, and shows in slots and Snippet blocks
     *
     * @test
     */
    public function it_tells_the_snippet_content_type(): void
    {
        $registry = new ContentTypeRegistry(['snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet']]);

        self::assertTrue($registry->get('snippet')->isSnippet());
        self::assertFalse($registry->get('snippet')->hasOwnUrl());
    }

    /**
     * @test
     */
    public function it_reads_the_field_the_meta_description_falls_back_to(): void
    {
        $registry = new ContentTypeRegistry(['post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'app.post']]);

        self::assertSame('excerpt', $registry->get('post')->descriptionField);
    }

    /**
     * @test
     */
    public function it_lets_a_prefix_set_in_config_override_the_attributes(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE, 'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe']], ['page' => 'pg']);

        self::assertSame('pg', $registry->get('page')->prefix);
        self::assertNull($registry->get('recipe')->prefix);
    }

    /**
     * @test
     */
    public function it_puts_the_urls_at_the_root_for_an_empty_prefix_set_in_config(): void
    {
        $registry = new ContentTypeRegistry(['article' => ['model' => Article::class, 'translation' => null, 'resource' => 'app.article']], ['article' => '']);

        self::assertSame('', $registry->get('article')->prefix);
        self::assertTrue($registry->get('article')->hasOwnUrl());
    }

    /**
     * @test
     */
    public function it_finds_a_content_type_by_an_entry_of_it_or_of_a_subclass(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertSame('page', $registry->getByEntry(new Page())->code);
        self::assertSame('page', $registry->getByEntry(new class() extends Page {
        })->code);
    }

    /**
     * A content type is named by a class, such as a collection's category type, by its model, a class the model
     * extends, as a project's model of a shipped content type extends the shipped one, or an interface it implements
     *
     * @test
     */
    public function it_finds_a_content_type_by_its_model_or_a_class_or_interface_its_model_extends(): void
    {
        $registry = new ContentTypeRegistry([
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'outlet' => ['model' => OutletLocation::class, 'translation' => null, 'resource' => 'app.outlet'],
            'page' => self::PAGE,
        ]);

        self::assertSame('recipe', $registry->getByClass(Recipe::class)->code);
        self::assertSame(OutletLocation::class, $registry->getByClass(OutletLocation::class)->modelClass);
        self::assertSame(OutletLocation::class, $registry->getByClass(Location::class)->modelClass);
        self::assertSame('page', $registry->getByClass(Page::class)->code);
        self::assertSame('page', $registry->getByClass(PageInterface::class)->code);
    }

    /**
     * @test
     */
    public function it_prefers_the_content_type_whose_model_is_the_class_over_one_whose_model_extends_it(): void
    {
        $registry = new ContentTypeRegistry([
            'outlet' => ['model' => OutletLocation::class, 'translation' => null, 'resource' => 'app.outlet'],
            'location' => ['model' => Location::class, 'translation' => null, 'resource' => 'app.location'],
        ]);

        self::assertSame(Location::class, $registry->getByClass(Location::class)->modelClass);
    }

    /**
     * @test
     */
    public function it_throws_on_a_class_no_content_type_s_model_is_or_extends(): void
    {
        $this->expectException(UnknownContentTypeException::class);
        $this->expectExceptionMessage(Snippet::class);

        (new ContentTypeRegistry(['page' => self::PAGE]))->getByClass(Snippet::class);
    }

    /**
     * @test
     */
    public function it_creates_a_translation_of_a_content_type_in_a_locale(): void
    {
        $translation = (new ContentTypeRegistry(['page' => self::PAGE]))->get('page')->createTranslation('da_DK');

        self::assertInstanceOf(PageTranslation::class, $translation);
        self::assertSame('da_DK', $translation->getLocale());
    }

    /**
     * @test
     */
    public function it_needs_a_translation_model_to_create_a_translation(): void
    {
        $recipe = (new ContentTypeRegistry(['recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe']]))->get('recipe');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "recipe" has no translation model to add the locale da_DK with');

        $recipe->createTranslation('da_DK');
    }

    /**
     * @test
     */
    public function it_reads_each_content_type_once(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertSame($registry->get('page'), $registry->get('page'));
    }

    /**
     * @test
     */
    public function it_finds_the_content_type_whose_model_the_entry_is(): void
    {
        $registry = new ContentTypeRegistry([
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'page' => self::PAGE,
        ]);

        self::assertSame('page', $registry->getByEntry(new Page())->code);
        self::assertSame('recipe', $registry->getByEntry(new Recipe())->code);
        self::assertFalse($registry->get('recipe')->hasOwnUrl());
        self::assertNull($registry->get('recipe')->blocks);
    }

    /**
     * @test
     */
    public function it_throws_when_a_model_has_no_content_type_attribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[ContentType]');

        (new ContentTypeRegistry(['untagged' => ['model' => Untagged::class, 'translation' => null, 'resource' => 'app.untagged']]))->get('untagged');
    }

    /**
     * @test
     */
    public function it_lists_every_content_type(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertTrue($registry->has('page'));
        self::assertFalse($registry->has('snippet'));
        self::assertSame(['page'], array_map(static fn ($contentType) => $contentType->code, $registry->all()));
    }

    /**
     * @test
     */
    public function it_throws_on_an_unknown_content_type(): void
    {
        $this->expectException(UnknownContentTypeException::class);

        (new ContentTypeRegistry([]))->get('snippet');
    }

    /**
     * @test
     */
    public function it_throws_on_an_entry_of_no_registered_content_type(): void
    {
        $this->expectException(UnknownContentTypeException::class);

        (new ContentTypeRegistry([]))->getByEntry(new Page());
    }
}
