<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;

/**
 * The FAQ's categories, the category content type of the FAQ: a title per translation and nothing else, ordered by
 * position. See "Shipped content types" in docs/spec/collections.md
 */
final class FaqCategoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_faq_category_content_type_without_a_url_or_a_tree_ordered_by_position(): void
    {
        $contentType = self::faqCategory();

        self::assertSame('faq_category', $contentType->code);
        self::assertSame('setono_sylius_cms.content_type.faq_category', $contentType->label);
        self::assertFalse($contentType->hasOwnUrl());
        self::assertNull($contentType->blocks, 'It has no block tree');
        self::assertTrue($contentType->order->byPosition);
        self::assertSame(['position' => 'ASC'], $contentType->order->fields);
        self::assertNull($contentType->categoryType);
        self::assertNull($contentType->descriptionField);
        self::assertTrue($contentType->isShipped());
    }

    /**
     * @test
     */
    public function it_has_only_a_title_per_translation(): void
    {
        $fields = new ContentTypeFields(new FieldReader());

        self::assertSame([], $fields->shared(self::faqCategory()));
        self::assertSame([], $fields->translatable(self::faqCategory()));

        $category = new FaqCategory();
        $category->setCurrentLocale('en_US');
        $category->setFallbackLocale('en_US');
        $category->setTitle('Shipping');

        self::assertSame(EntryTranslation::class, $category->getTranslation('en_US')::class);
        self::assertSame('Shipping', $category->getTitle());
    }

    /**
     * @test
     */
    public function it_starts_with_an_empty_tree_without_a_layout(): void
    {
        $tree = (new FaqCategory())->getBlockTree();

        self::assertNull($tree->layout);
        self::assertSame([], $tree->blocks);
    }

    private static function faqCategory(): ContentTypeMetadata
    {
        return (new ContentTypeRegistry([
            'faq_category' => ['model' => FaqCategory::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.faq_category'],
        ]))->get('faq_category');
    }
}
