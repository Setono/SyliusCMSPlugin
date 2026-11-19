<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Entry;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Source\Entry\EntriesFromCollectionParams;
use Setono\SyliusCMSPlugin\Source\Entry\EntriesFromCollectionSource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * "From a collection": one collection's visible entries, optionally only those in any of the chosen categories, in the
 * content type's order, then by ID. See "Entry sources" in docs/spec/sources.md
 */
final class EntriesFromCollectionSourceTest extends TestCase
{
    use EntrySources;
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_from_a_collection_source_of_entries_with_its_parameters(): void
    {
        $attribute = AttributeReader::source(EntriesFromCollectionSource::class);

        self::assertNotNull($attribute);
        self::assertSame('entries_from_collection', $attribute->code);
        self::assertSame('setono_sylius_cms.source.entries_from_collection', $attribute->label);
        self::assertSame(EntryInterface::class, $attribute->of);
        self::assertSame([], $attribute->needs);
        self::assertSame(EntriesFromCollectionParams::class, $attribute->params);
    }

    /**
     * @test
     */
    public function it_lists_the_content_type_its_parameter_holds_wherever_it_is(): void
    {
        $source = $this->source();

        self::assertSame('faq_item', $source->getListedContentType(self::params('faq_item'), null));
        self::assertSame('blog_post', $source->getListedContentType(self::params('blog_post'), 'faq_category'));
        self::assertNull($source->getListedContentType(self::params(null), 'blog_post'), 'It can\'t tell without a collection');
        self::assertNull($source->getListedContentType(new \stdClass(), null));
    }

    /**
     * @test
     */
    public function it_pages_the_collection_s_visible_entries_in_its_content_type_s_order_then_by_id_in_sql(): void
    {
        $adapter = $this->source()->resolve(self::params('blog_post'), Rendering::context());

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled ORDER BY entry.publishedAt DESC, entry.id ASC',
            BlogPost::class,
        ), $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_a_collection_ordered_by_position_by_the_position_editors_dragged_its_entries_into(): void
    {
        $this->source()->resolve(self::params('faq_item'), Rendering::context());

        self::assertSame(sprintf(
            'SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled ORDER BY entry.position ASC, entry.id ASC',
            FaqItem::class,
        ), $this->dql());
    }

    /**
     * Category filters join the indexed reference table, so they page in SQL, and each entry is listed once however
     * many of the categories it's in
     *
     * @test
     */
    public function it_keeps_only_the_entries_in_any_of_the_chosen_categories_through_the_reference_table(): void
    {
        $news = new BlogCategory();
        $guides = new BlogCategory();

        $this->source()->resolve(self::params('blog_post', [$news, $guides]), Rendering::context());

        self::assertSame(sprintf(
            'SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled AND (EXISTS (SELECT cms_reference_1.id FROM %s cms_reference_1 WHERE cms_reference_1.entry = entry AND cms_reference_1.uuid IN (:cms_reference_1_uuids) AND cms_reference_1.field = :cms_reference_1_field)) ORDER BY entry.publishedAt DESC, entry.id ASC',
            BlogPost::class,
            EntryReferenceInterface::class,
        ), $this->dql());
        self::assertSame([$news->getUuid(), $guides->getUuid()], $this->parameter('cms_reference_1_uuids'));
        self::assertSame('categories', $this->parameter('cms_reference_1_field'));
    }

    /**
     * The chosen categories the shopper may no longer see, or that were deleted, leave none to be in
     *
     * @test
     */
    public function it_lists_nothing_when_none_of_the_chosen_categories_shows(): void
    {
        $this->source()->resolve(self::params('blog_post', []), Rendering::context());

        self::assertStringContainsString('AND 1 = 0', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_in_categories_of_a_collection_without_categories(): void
    {
        $this->source()->resolve(self::params('shop', [new BlogCategory()]), Rendering::context());

        self::assertSame(sprintf('SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled AND 1 = 0 ORDER BY entry.createdAt DESC, entry.id ASC', Shop::class), $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_without_a_collection(): void
    {
        $adapter = $this->source()->resolve(self::params(null), Rendering::context());

        self::assertInstanceOf(ArrayAdapter::class, $adapter);
        self::assertSame(0, $adapter->getNbResults());
        self::assertSame([], $this->queries);
    }

    private function source(): EntriesFromCollectionSource
    {
        return new EntriesFromCollectionSource($this->visibleEntries(self::MODELS), self::contentTypes(), self::indexedFields());
    }

    /**
     * @param list<EntryInterface>|null $categories
     */
    private static function params(?string $contentType, ?array $categories = null): EntriesFromCollectionParams
    {
        $params = new EntriesFromCollectionParams();
        $params->contentType = $contentType;
        $params->categories = null === $categories ? null : ItemList::of($categories);

        return $params;
    }
}
