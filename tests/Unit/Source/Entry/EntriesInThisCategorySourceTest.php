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
use Setono\SyliusCMSPlugin\Source\Entry\EntriesInThisCategorySource;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;

/**
 * "In this category": on a category's page, the entries of its collection the shopper may see that are in it, in the
 * collection's order. See "Entry sources" in docs/spec/sources.md
 */
final class EntriesInThisCategorySourceTest extends TestCase
{
    use EntrySources;
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_in_this_category_source_of_entries_that_needs_the_entry_without_parameters(): void
    {
        $attribute = AttributeReader::source(EntriesInThisCategorySource::class);

        self::assertNotNull($attribute);
        self::assertSame('entries_in_this_category', $attribute->code);
        self::assertSame('setono_sylius_cms.source.entries_in_this_category', $attribute->label);
        self::assertSame(EntryInterface::class, $attribute->of);
        self::assertSame(['entry'], $attribute->needs);
        self::assertNull($attribute->params);
    }

    /**
     * @test
     */
    public function it_fits_only_a_category_as_the_entry(): void
    {
        $source = $this->source();

        self::assertTrue($source->supportsEntryContentType('blog_category'));
        self::assertTrue($source->supportsEntryContentType('faq_category'));
        self::assertFalse($source->supportsEntryContentType('blog_post'));
        self::assertFalse($source->supportsEntryContentType('shop'));
        self::assertFalse($source->supportsEntryContentType('page'));
        self::assertFalse($source->supportsEntryContentType('removed'));
    }

    /**
     * @test
     */
    public function it_lists_the_content_type_whose_category_type_the_entry_is(): void
    {
        $source = $this->source();

        self::assertSame('blog_post', $source->getListedContentType(new NoParams(), 'blog_category'));
        self::assertSame('faq_item', $source->getListedContentType(new NoParams(), 'faq_category'));
        self::assertNull($source->getListedContentType(new NoParams(), 'page'));
        self::assertNull($source->getListedContentType(new NoParams(), null), 'It can\'t tell outside an entry');
    }

    /**
     * @test
     */
    public function it_pages_the_visible_entries_in_the_category_through_the_reference_table_in_the_collection_s_order_in_sql(): void
    {
        $category = new BlogCategory();

        $adapter = $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: ['entry' => $category]));

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertSame(sprintf(
            'SELECT entry FROM %s entry WHERE entry.enabled = :cms_visible_enabled AND (EXISTS (SELECT cms_reference_1.id FROM %s cms_reference_1 WHERE cms_reference_1.entry = entry AND cms_reference_1.uuid IN (:cms_reference_1_uuids) AND cms_reference_1.field = :cms_reference_1_field)) ORDER BY entry.publishedAt DESC, entry.id ASC',
            BlogPost::class,
            EntryReferenceInterface::class,
        ), $this->dql());
        self::assertSame([$category->getUuid()], $this->parameter('cms_reference_1_uuids'));
        self::assertSame('categories', $this->parameter('cms_reference_1_field'));
    }

    /**
     * @test
     */
    public function it_lists_the_faq_items_in_a_faq_category_by_their_position(): void
    {
        $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: ['entry' => new FaqCategory()]));

        self::assertStringStartsWith(sprintf('SELECT entry FROM %s entry', FaqItem::class), $this->dql());
        self::assertStringEndsWith('ORDER BY entry.position ASC, entry.id ASC', $this->dql());
    }

    /**
     * @test
     */
    public function it_lists_nothing_outside_a_category(): void
    {
        foreach ([[], ['entry' => new Page()], ['entry' => new BlogPost()]] as $hostPageContext) {
            $adapter = $this->source()->resolve(new NoParams(), Rendering::context(hostPageContext: $hostPageContext));

            self::assertInstanceOf(ArrayAdapter::class, $adapter);
            self::assertSame(0, $adapter->getNbResults());
        }

        self::assertSame([], $this->queries);
    }

    private function source(): EntriesInThisCategorySource
    {
        return new EntriesInThisCategorySource($this->visibleEntries(self::models()), self::contentTypes(), self::collections(), self::indexedFields());
    }
}
