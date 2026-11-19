<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;

/**
 * The FAQ's items: no URL, no block tree, ordered by position, each in any of the FAQ's categories. See "Shipped content
 * types" in docs/spec/collections.md
 */
final class FaqItemTest extends TestCase
{
    private const SHIPPING = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const RETURNS = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7c';

    /**
     * @test
     */
    public function it_is_the_faq_item_content_type_without_a_url_or_a_tree_ordered_by_position_with_faq_categories(): void
    {
        $contentType = self::faqItem();

        self::assertSame('faq_item', $contentType->code);
        self::assertSame('setono_sylius_cms.content_type.faq_item', $contentType->label);
        self::assertFalse($contentType->hasOwnUrl());
        self::assertNull($contentType->blocks, 'It has no block tree');
        self::assertTrue($contentType->order->byPosition);
        self::assertSame(['position' => 'ASC'], $contentType->order->fields);
        self::assertSame(FaqCategory::class, $contentType->categoryType);
        self::assertNull($contentType->descriptionField, 'Without a URL it has no meta description');
        self::assertTrue($contentType->isShipped());
    }

    /**
     * Its title is the question, and its answer is rich text in each translation. Its categories are hand-picked FAQ
     * categories, shared by its translations and indexed, as a collection's categories are
     *
     * @test
     */
    public function it_has_a_translatable_rich_text_answer_and_hand_picked_indexed_faq_categories(): void
    {
        $fields = new ContentTypeFields(new FieldReader());

        $translatable = $fields->translatable(self::faqItem());
        self::assertSame(['answer'], array_keys($translatable));
        self::assertInstanceOf(RichText::class, $translatable['answer']->field);
        self::assertTrue($translatable['answer']->translatable);

        $shared = $fields->shared(self::faqItem());
        self::assertSame(['categories'], array_keys($shared));
        $categories = $shared['categories']->field;
        self::assertInstanceOf(Entries::class, $categories);
        self::assertSame(FaqCategory::class, $categories->contentType);
        self::assertTrue($categories->indexed);
        self::assertFalse($categories->sources, 'Categories are hand-picked only');
        self::assertFalse($shared['categories']->translatable);

        self::assertSame('categories', (new IndexedFields(self::registry(), $fields))->categoryField(self::faqItem())?->name);
    }

    /**
     * @test
     */
    public function it_keeps_its_categories_in_the_editors_order_and_its_answer_on_its_translations(): void
    {
        $item = new FaqItem();
        $item->setCategories([self::RETURNS, self::SHIPPING]);
        self::assertSame([self::RETURNS, self::SHIPPING], $item->getCategories());

        $item->setCategories([]);
        self::assertSame([], $item->getCategories());

        $item->setCurrentLocale('en_US');
        $item->setFallbackLocale('en_US');
        $translation = $item->getTranslation('en_US');
        self::assertInstanceOf(FaqItemTranslation::class, $translation);

        $answer = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Within three days.']]]]];
        $translation->setAnswer($answer);
        self::assertSame($answer, $translation->getAnswer());
    }

    /**
     * @test
     */
    public function it_starts_with_an_empty_tree_without_a_layout(): void
    {
        $tree = (new FaqItem())->getBlockTree();

        self::assertNull($tree->layout);
        self::assertSame([], $tree->blocks);
    }

    private static function faqItem(): ContentTypeMetadata
    {
        return self::registry()->get('faq_item');
    }

    private static function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
            'faq_category' => ['model' => FaqCategory::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.faq_category'],
        ]);
    }
}
