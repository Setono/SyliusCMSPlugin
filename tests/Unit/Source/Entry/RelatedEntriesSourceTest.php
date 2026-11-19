<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Entry;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Source\Entry\RelatedEntriesSource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;

/**
 * "Related": the other visible entries of the host page's entry's content type that share a category with it, newest
 * first. See "Entry sources" in docs/spec/sources.md
 */
final class RelatedEntriesSourceTest extends TestCase
{
    use EntrySources;
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_related_source_of_entries_that_needs_the_entry_without_parameters(): void
    {
        $attribute = AttributeReader::source(RelatedEntriesSource::class);

        self::assertNotNull($attribute);
        self::assertSame('related_entries', $attribute->code);
        self::assertSame('setono_sylius_cms.source.related_entries', $attribute->label);
        self::assertSame(EntryInterface::class, $attribute->of);
        self::assertSame(['entry'], $attribute->needs);
        self::assertNull($attribute->params);
    }

    /**
     * @test
     */
    public function it_fits_only_an_entry_of_a_collection_with_categories(): void
    {
        $source = $this->source();

        self::assertTrue($source->supportsEntryContentType('blog_post'));
        self::assertTrue($source->supportsEntryContentType('faq_item'));
        self::assertFalse($source->supportsEntryContentType('shop'), 'A collection without categories');
        self::assertFalse($source->supportsEntryContentType('blog_category'));
        self::assertFalse($source->supportsEntryContentType('page'));
        self::assertFalse($source->supportsEntryContentType('removed'));
    }

    /**
     * @test
     */
    public function it_lists_the_entry_s_own_content_type(): void
    {
        $source = $this->source();

        self::assertSame('blog_post', $source->getListedContentType(new NoParams(), 'blog_post'));
        self::assertSame('faq_item', $source->getListedContentType(new NoParams(), 'faq_item'));
        self::assertNull($source->getListedContentType(new NoParams(), 'page'));
        self::assertNull($source->getListedContentType(new NoParams(), null), 'It can\'t tell outside an entry');
    }

    /**
     * Blog post's order is its date descending, so that's newest first
     *
     * @test
     */
    public function it_pages_the_other_visible_entries_in_any_of_the_entry_s_categories_newest_first_by_its_date_in_sql(): void
    {
        $news = new BlogCategory();
        $guides = new BlogCategory();
        $post = new BlogPost();
        $post->setCategories([$news->getUuid(), $guides->getUuid()]);

        $adapter = $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: ['entry' => $post]));

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled AND (EXISTS (SELECT cms_reference_1.id FROM %s cms_reference_1 WHERE cms_reference_1.entry = entry AND cms_reference_1.uuid IN (:cms_reference_1_uuids) AND cms_reference_1.field = :cms_reference_1_field)) AND entry.uuid <> :cms_source_host_entry ORDER BY entry.publishedAt DESC, entry.id ASC',
            BlogPost::class,
            EntryReferenceInterface::class,
        ), $this->dql());
        self::assertSame([$news->getUuid(), $guides->getUuid()], $this->parameter('cms_reference_1_uuids'));
        self::assertSame('categories', $this->parameter('cms_reference_1_field'));
        self::assertSame($post->getUuid(), $this->parameter('cms_source_host_entry'));
    }

    /**
     * FAQ items are ordered by position, which isn't a date, so newest first is by when they were created
     *
     * @test
     */
    public function it_lists_newest_first_by_the_creation_date_when_the_content_type_s_order_is_not_a_date_descending(): void
    {
        $item = new FaqItem();
        $item->setCategories([(new FaqCategory())->getUuid()]);

        $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: ['entry' => $item]));

        self::assertStringStartsWith(sprintf('SELECT entry FROM %s entry', FaqItem::class), $this->dql());
        self::assertStringEndsWith('ORDER BY entry.createdAt DESC, entry.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_for_an_entry_in_no_category_or_outside_a_collection_with_categories(): void
    {
        foreach ([[], ['entry' => new BlogPost()], ['entry' => new Page()], ['entry' => new Shop()], ['entry' => new BlogCategory()]] as $hostPageContext) {
            $adapter = $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: $hostPageContext));

            self::assertInstanceOf(ArrayAdapter::class, $adapter);
            self::assertSame(0, $adapter->getNbResults());
        }

        self::assertSame([], $this->queries);
    }

    private function source(): RelatedEntriesSource
    {
        return new RelatedEntriesSource($this->visibleEntries(self::models()), self::contentTypes(), self::collections(), self::indexedFields());
    }
}
