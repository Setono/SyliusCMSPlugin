<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Collection\CollectionNames;
use Setono\SyliusCMSPlugin\Collection\ListedContentTypes;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Source\Entry\CollectionChoicesProvider;
use Setono\SyliusCMSPlugin\Source\Entry\EntriesFromCollectionSource;
use Setono\SyliusCMSPlugin\Source\Entry\EntriesInThisCategorySource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Entry\EntrySources;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\NearbyShops;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\NewPosts;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Which content types an Entries list holds, so the Entries block offers their card displays: the type a source lists,
 * every picked entry's type when it's hand-picked, or none it can tell. See "The Entries block" in
 * docs/spec/collections.md and "Sources" in docs/spec/extending.md
 */
final class ListedContentTypesTest extends TestCase
{
    use EntrySources;
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_tells_the_content_type_of_every_picked_entry_the_shopper_may_see(): void
    {
        $post = new BlogPost();
        $otherPost = new BlogPost();
        $item = new FaqItem();
        $references = new ReferenceMap([ReferenceType::ENTRY => [$post->getUuid() => $post, $otherPost->getUuid() => $otherPost, $item->getUuid() => $item]]);

        self::assertSame(['blog_post', 'faq_item'], $this->listed()->of([$post->getUuid(), $otherPost->getUuid(), $item->getUuid(), '0193a5be-0000-7000-8000-00000000dead'], self::context($references)));
        self::assertNull($this->listed()->of(['0193a5be-0000-7000-8000-00000000dead'], self::context()), 'None of the picks shows');
        self::assertNull($this->listed()->of([], self::context()));
        self::assertNull($this->listed()->of(null, self::context()));
    }

    /**
     * @test
     */
    public function it_tells_the_content_type_a_source_lists_with_its_parameters_on_the_entry_s_page(): void
    {
        $listed = $this->listed();

        self::assertSame(['faq_item'], $listed->of(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'faq_item'], '$limit' => 9], self::context()));
        self::assertSame(['blog_post'], $listed->of(['$source' => 'entries_in_this_category', '$params' => [], '$limit' => 9], self::context(entry: new BlogCategory())));
        self::assertNull($listed->of(['$source' => 'entries_in_this_category', '$params' => [], '$limit' => 9], self::context()), 'It can\'t tell outside an entry');
    }

    /**
     * Without ListedContentTypeAwareInterface, it lists the content type its #[AsSource(of:)] names, or any
     *
     * @test
     */
    public function it_tells_the_content_type_a_source_names_as_what_it_lists(): void
    {
        $listed = $this->listed();

        self::assertSame(['blog_post'], $listed->of(['$source' => 'new_posts', '$params' => [], '$limit' => 9], self::context()));
        self::assertNull($listed->of(['$source' => 'nearby_shops', '$params' => [], '$limit' => 9], self::context()));
        self::assertNull($listed->of(['$source' => 'removed', '$params' => [], '$limit' => 9], self::context()));
    }

    /**
     * A picked entry of a content type that's no longer registered, and a source of what isn't an entry of a content
     * type, have no card displays to offer
     *
     * @test
     */
    public function it_cannot_tell_the_content_type_of_an_entry_or_a_source_of_a_class_no_content_type_is(): void
    {
        $listed = $this->listed();
        $recipe = new Recipe();

        self::assertNull($listed->of([$recipe->getUuid()], self::context(new ReferenceMap([ReferenceType::ENTRY => [$recipe->getUuid() => $recipe]]))));
        self::assertNull($listed->of(['$source' => 'on_sale', '$params' => [], '$limit' => 9], self::context()));
        self::assertNull($listed->of(['$source' => 'entries_in_this_category', '$params' => [], '$limit' => 9], self::context(entry: $recipe)), 'The host page is of no content type');
    }

    /**
     * @test
     */
    public function it_lists_the_references_to_load_for_a_hand_picked_list(): void
    {
        $uuid = '0193a5be-0000-7000-8000-0000000000a1';

        self::assertEquals([new Reference(ReferenceType::ENTRY, $uuid, false)], $this->listed()->references([$uuid]));
        self::assertSame([], $this->listed()->references(['$source' => 'entries_from_collection', '$params' => ['contentType' => 'faq_item'], '$limit' => 9]));
    }

    private function listed(): ListedContentTypes
    {
        $visibleEntries = $this->prophesize(VisibleEntriesQueryInterface::class)->reveal();

        return new ListedContentTypes(
            Sources::registry([
                'entries_from_collection' => new EntriesFromCollectionSource($visibleEntries, self::contentTypes(), self::indexedFields()),
                'entries_in_this_category' => new EntriesInThisCategorySource($visibleEntries, self::contentTypes(), self::collections(), self::indexedFields()),
                'new_posts' => new NewPosts(),
                'nearby_shops' => new NearbyShops(),
                'on_sale' => new OnSale(),
            ]),
            Sources::paramsHydrator([CollectionChoicesProvider::class => new CollectionChoicesProvider(self::collections(), new CollectionNames(new IdentityTranslator()))], self::contentTypes()),
            self::contentTypes(),
        );
    }

    private static function context(?ReferenceMap $references = null, ?object $entry = null): HydrationContext
    {
        return new HydrationContext(Rendering::context(hostPageContext: null === $entry ? [] : ['entry' => $entry]), $references ?? new ReferenceMap());
    }
}
