<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Model\FaqCategoryInterface;
use Setono\SyliusCMSPlugin\Model\FaqItemInterface;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslationInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Helpers for the FAQ: categories and items, created in en_US through their resources' factories, so like any new
 * entry they're in every channel, and last in their content type's order by position. A published one is published
 * through the publisher, as the builder's Publish does, so its categories are written to the reference table
 */
trait Faq
{
    use CollectionEntries;

    protected static function createFaqCategory(string $title, bool $published = false): FaqCategoryInterface
    {
        /** @var FactoryInterface<FaqCategoryInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.faq_category');

        $category = $factory->createNew();
        $category->setCurrentLocale('en_US');
        $category->setFallbackLocale('en_US');
        $category->setTitle($title);

        return self::saveCollectionEntry($category, $published);
    }

    /**
     * @param string $question its title
     * @param string|null $answer the answer's text, as one paragraph of rich text
     * @param list<FaqCategoryInterface|string> $categories the categories, or their UUIDs, in the editor's order
     */
    protected static function createFaqItem(string $question, ?string $answer = null, array $categories = [], bool $published = false): FaqItemInterface
    {
        /** @var FactoryInterface<FaqItemInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.faq_item');

        $item = $factory->createNew();
        $item->setCurrentLocale('en_US');
        $item->setFallbackLocale('en_US');
        $item->setTitle($question);
        $item->setCategories(array_map(static fn (FaqCategoryInterface|string $category): string => is_string($category) ? $category : $category->getUuid(), $categories));

        $translation = $item->getTranslation('en_US');
        self::assertInstanceOf(FaqItemTranslationInterface::class, $translation);
        $translation->setAnswer(null === $answer ? null : self::answer($answer));

        return self::saveCollectionEntry($item, $published);
    }

    /**
     * A rich text document of one paragraph, as an answer is stored
     *
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    protected static function answer(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }
}
