<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\Doctrine\ColumnPrefixListener;
use Setono\SyliusCMSPlugin\Doctrine\DiscriminatorMapListener;
use Setono\SyliusCMSPlugin\Doctrine\RootAssociationsListener;
use Setono\SyliusCMSPlugin\Factory\EntryFactory;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\CardedOddly;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\CardedTwice;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\OrderedByMissing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\OrderedUpwards;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SeasonalRecipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Untagged;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Workshop;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\PostTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterContentTypesPassTest extends AbstractCompilerPassTestCase
{
    private const PAGE = [
        'classes' => ['model' => Page::class],
        'translation' => ['classes' => ['model' => PageTranslation::class]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[], []]));
        $this->setDefinition(DiscriminatorMapListener::class, new Definition(DiscriminatorMapListener::class, [[]]));
        $this->setDefinition(ColumnPrefixListener::class, new Definition(ColumnPrefixListener::class, [[]]));
        $this->setDefinition(RootAssociationsListener::class, new Definition(RootAssociationsListener::class, [[]]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterContentTypesPass());
    }

    /**
     * @test
     */
    public function it_finds_the_content_types_among_the_sylius_resources(): void
    {
        $this->setParameter('sylius.resources', [
            'sylius.product' => ['classes' => ['model' => \stdClass::class]],
            // The root of the content types is a resource too, for the admin's entry lists
            'setono_sylius_cms.entry' => ['classes' => ['model' => Entry::class]],
            'setono_sylius_cms.page' => self::PAGE,
            'app.recipe.v2' => ['classes' => ['model' => Recipe::class], 'translation' => ['classes' => ['model' => 'App\Missing']]],
            'app.broken' => ['classes' => []],
        ]);

        $this->compile();

        $contentTypes = [
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe.v2'],
        ];
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, $contentTypes);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 1, []);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DiscriminatorMapListener::class, 0, $contentTypes);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ColumnPrefixListener::class, 0, $contentTypes);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(RootAssociationsListener::class, 0, $contentTypes);

        $locator = $this->container->getDefinition((string) $this->container->getAlias('setono_sylius_cms.entry_factories'));
        self::assertEquals([
            'page' => new ServiceClosureArgument(new Reference('setono_sylius_cms.factory.page')),
            'recipe' => new ServiceClosureArgument(new Reference('app.factory.recipe.v2')),
        ], $locator->getArgument(0));
    }

    /**
     * @test
     */
    public function it_decorates_each_content_types_factory_so_a_new_entry_starts_in_every_channel(): void
    {
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE, 'app.recipe.v2' => ['classes' => ['model' => Recipe::class]]]);

        $this->compile();

        foreach (['page' => 'setono_sylius_cms.factory.page', 'recipe' => 'app.factory.recipe.v2'] as $code => $factory) {
            $decorator = 'setono_sylius_cms.entry_factory.' . $code;
            $this->assertContainerBuilderHasService($decorator, EntryFactory::class);
            $this->assertContainerBuilderHasServiceDefinitionWithArgument($decorator, 0, new Reference($decorator . '.inner'));
            $this->assertContainerBuilderHasServiceDefinitionWithArgument($decorator, 1, new Reference('sylius.repository.channel'));
            $this->assertContainerBuilderHasServiceDefinitionWithArgument($decorator, 2, new Reference(ChannelsAndAudienceModifierInterface::class));
            self::assertSame([$factory, null, 0], $this->container->getDefinition($decorator)->getDecoratedService());
        }
    }

    /**
     * @test
     */
    public function it_hands_the_registry_the_prefixes_set_in_config(): void
    {
        $this->setParameter('sylius.resources', [
            'setono_sylius_cms.page' => self::PAGE,
            'app.article' => ['classes' => ['model' => Article::class]],
            'app.recipe' => ['classes' => ['model' => Recipe::class]],
        ]);
        // A content type the config sets no prefix for keeps the attribute's, even one without a URL of its own
        $this->setParameter(RegisterContentTypesPass::CONFIG_PARAMETER, ['recipe' => [], 'page' => ['prefix' => 'pg'], 'article' => ['prefix' => '']]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 1, ['page' => 'pg', 'article' => '']);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_prefix_set_in_config_for_a_code_no_content_type_has(): void
    {
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE]);
        $this->setParameter(RegisterContentTypesPass::CONFIG_PARAMETER, ['blog_post' => ['prefix' => 'news']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setono_sylius_cms.content_types sets a prefix for "blog_post", which is no content type');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_prefix_set_in_config_for_a_content_type_without_a_url_of_its_own(): void
    {
        $this->setParameter('sylius.resources', ['app.recipe' => ['classes' => ['model' => Recipe::class]]]);
        $this->setParameter(RegisterContentTypesPass::CONFIG_PARAMETER, ['recipe' => ['prefix' => 'recipes']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setono_sylius_cms.content_types sets a prefix for "recipe", which has no URL of its own');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_lets_a_content_type_name_a_property_of_its_translations_for_the_meta_description_to_fall_back_to(): void
    {
        $this->setParameter('sylius.resources', ['app.post' => ['classes' => ['model' => Post::class], 'translation' => ['classes' => ['model' => PostTranslation::class]]]]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, [
            'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'app.post'],
        ]);
    }

    /**
     * @test
     *
     * @dataProvider provideTranslationsWithoutTheDescriptionField
     *
     * @param array<string, mixed> $resource
     */
    public function it_fails_the_build_for_a_field_for_the_meta_description_to_fall_back_to_that_its_translations_do_not_have(array $resource): void
    {
        $this->setParameter('sylius.resources', ['app.post' => ['classes' => ['model' => Post::class]] + $resource]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "post" names "excerpt" in descriptionField, which must be a property of its translations');

        $this->compile();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideTranslationsWithoutTheDescriptionField(): iterable
    {
        yield 'translations without it' => [['translation' => ['classes' => ['model' => EntryTranslation::class]]]];
        yield 'no translation model' => [[]];
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_an_order_that_is_neither_position_nor_properties_with_a_direction(): void
    {
        $this->setParameter('sylius.resources', ['app.ordered_upwards' => ['classes' => ['model' => OrderedUpwards::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "ordered_upwards" has an invalid order: The order orders "createdAt" by "UP", which must be \'ASC\' or \'DESC\'');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_an_order_by_a_property_the_entries_do_not_have(): void
    {
        $this->setParameter('sylius.resources', ['app.ordered_by_missing' => ['classes' => ['model' => OrderedByMissing::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "ordered_by_missing" orders its entries by "missing", which must be a property of OrderedByMissing');

        $this->compile();
    }

    /**
     * A card's display is printed in the Entries block's class, cms-entries--<display>
     *
     * @test
     */
    public function it_fails_the_build_for_a_card_whose_display_is_not_a_code(): void
    {
        $this->setParameter('sylius.resources', ['app.carded_oddly' => ['classes' => ['model' => CardedOddly::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "carded_oddly" has a card for the display "Big grid", which must match /^[a-z][a-z0-9_]*$/');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_cards_for_one_display(): void
    {
        $this->setParameter('sylius.resources', ['app.carded_twice' => ['classes' => ['model' => CardedTwice::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "carded_twice" has more than one card for the display "grid"');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_lets_a_content_type_name_another_content_type_as_its_category_type(): void
    {
        $this->setParameter('sylius.resources', [
            'app.location' => ['classes' => ['model' => Location::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]],
            'app.recipe' => ['classes' => ['model' => Recipe::class]],
        ]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, [
            'location' => ['model' => Location::class, 'translation' => EntryTranslation::class, 'resource' => 'app.location'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_category_type_that_is_no_content_type(): void
    {
        $this->setParameter('sylius.resources', ['app.location' => ['classes' => ['model' => Location::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The content type "location" names %s in categoryType, which must be the model of a content type, or a class or interface one extends', Recipe::class));

        $this->compile();
    }

    /**
     * A project overrides a shipped category content type's model with its own subclass, which the collection's
     * categoryType still names
     *
     * @test
     */
    public function it_lets_a_category_type_name_a_class_the_category_content_type_s_model_extends(): void
    {
        $this->setParameter('sylius.resources', [
            'app.location' => ['classes' => ['model' => Location::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]],
            'app.recipe' => ['classes' => ['model' => SeasonalRecipe::class]],
        ]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, [
            'location' => ['model' => Location::class, 'translation' => EntryTranslation::class, 'resource' => 'app.location'],
            'recipe' => ['model' => SeasonalRecipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);
    }

    /**
     * Listings filter a collection's entries by category on the reference table, so its category field is indexed
     * (docs/spec/extending.md#content-types)
     *
     * @test
     */
    public function it_fails_the_build_for_a_category_type_without_an_indexed_entries_field_of_it(): void
    {
        $this->setParameter('sylius.resources', [
            'app.workshop' => ['classes' => ['model' => Workshop::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]],
            'app.recipe' => ['classes' => ['model' => Recipe::class]],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The content type "workshop" names %s in categoryType, so it must have one Entries field of its own with that contentType and indexed: true, which holds its categories', Recipe::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_hands_the_registry_the_content_types_switched_off_in_config(): void
    {
        $this->setParameter('sylius.resources', [
            'setono_sylius_cms.page' => self::PAGE,
            'setono_sylius_cms.post' => ['classes' => ['model' => Post::class], 'translation' => ['classes' => ['model' => PostTranslation::class]]],
        ]);
        $this->setParameter(RegisterContentTypesPass::DISABLED_PARAMETER, ['post']);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 2, ['post']);
    }

    /**
     * A collection is switched off as a whole: its entries' content type with the category content type it names
     *
     * @test
     */
    public function it_hands_the_registry_a_collection_switched_off_as_a_whole(): void
    {
        $this->setParameter('sylius.resources', [
            'app.location' => ['classes' => ['model' => Location::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]],
            'app.recipe' => ['classes' => ['model' => SeasonalRecipe::class]],
        ]);
        $this->setParameter(RegisterContentTypesPass::DISABLED_PARAMETER, ['recipe', 'location']);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 2, ['recipe', 'location']);
    }

    /**
     * Switching off one would leave the other a collection of its own, such as the blog's categories listed as
     * "Categories" without their posts
     *
     * @test
     *
     * @dataProvider providePartsOfACollection
     */
    public function it_fails_the_build_for_switching_off_some_but_not_all_of_a_collections_content_types(string $code, string $message): void
    {
        $this->setParameter('sylius.resources', [
            'app.location' => ['classes' => ['model' => Location::class], 'translation' => ['classes' => ['model' => EntryTranslation::class]]],
            'app.recipe' => ['classes' => ['model' => SeasonalRecipe::class]],
        ]);
        $this->setParameter(RegisterContentTypesPass::DISABLED_PARAMETER, [$code]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->compile();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePartsOfACollection(): iterable
    {
        yield 'its entries' => ['location', 'setono_sylius_cms.disabled.content_types switches off "location" but not "recipe", its collection\'s categories. A collection is switched off as a whole, so add "recipe" too'];
        yield 'its categories' => ['recipe', 'setono_sylius_cms.disabled.content_types switches off "recipe", the categories of the collection "location", but not "location". A collection is switched off as a whole, so add "location" too'];
    }

    /**
     * @test
     *
     * @dataProvider provideContentTypesThatCannotBeSwitchedOff
     */
    public function it_fails_the_build_for_switching_off_a_built_in_content_type_or_a_code_no_content_type_has(string $code, string $message): void
    {
        $this->setParameter('sylius.resources', [
            'setono_sylius_cms.page' => self::PAGE,
            'setono_sylius_cms.snippet' => ['classes' => ['model' => Snippet::class]],
        ]);
        $this->setParameter(RegisterContentTypesPass::DISABLED_PARAMETER, [$code]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->compile();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideContentTypesThatCannotBeSwitchedOff(): iterable
    {
        yield 'page' => ['page', 'setono_sylius_cms.disabled.content_types switches off "page", a built-in content type, which can\'t be switched off'];
        yield 'snippet' => ['snippet', 'setono_sylius_cms.disabled.content_types switches off "snippet", a built-in content type, which can\'t be switched off'];
        yield 'unknown' => ['blog_post', 'setono_sylius_cms.disabled.content_types switches off "blog_post", which is no content type'];
    }

    /**
     * @test
     */
    public function two_content_types_with_the_same_code_fail_the_build(): void
    {
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE, 'app.page' => self::PAGE]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type code "page" is used by both the resources "setono_sylius_cms.page" and "app.page"');

        $this->compile();
    }

    /**
     * @test
     */
    public function an_entry_resource_must_carry_the_attribute(): void
    {
        $this->setParameter('sylius.resources', ['app.untagged' => ['classes' => ['model' => Untagged::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must carry #[ContentType]');

        $this->compile();
    }

    /**
     * @test
     */
    public function a_content_type_code_must_be_a_valid_code(): void
    {
        $this->setParameter('sylius.resources', ['app.invalid' => ['classes' => ['model' => InvalidCode::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Invalid-Code"');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_sylius_resources(): void
    {
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, []);
        self::assertFalse($this->container->hasAlias('setono_sylius_cms.entry_factories'));
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(ContentTypeRegistry::class);
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DiscriminatorMapListener::class, 0, []);
    }
}
