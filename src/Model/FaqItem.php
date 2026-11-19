<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Entries;

/**
 * The FAQ's items: no URL and no block tree, so they're edited in the builder without the tree pane, ordered by the
 * position editors drag them into in their admin list, in the FAQ's categories. The title is the question and the
 * answer is on the translations. Their columns start with faq_item_, such as faq_item_categories. See "Shipped
 * content types" in docs/spec/collections.md
 */
#[ContentType(
    code: self::CONTENT_TYPE,
    label: 'setono_sylius_cms.content_type.faq_item',
    cards: [new Card('accordion', '@SetonoSyliusCMSPlugin/card/faq_item/accordion.html.twig')],
    order: ContentType::ORDER_BY_POSITION,
    categoryType: FaqCategory::class,
)]
class FaqItem extends Entry implements FaqItemInterface
{
    /** @var list<string>|null */
    #[Entries(label: 'setono_sylius_cms.field.faq_item.categories', contentType: FaqCategory::class, indexed: true, sources: false)]
    protected ?array $categories = null;

    public function getCategories(): array
    {
        return array_values(array_filter($this->categories ?? [], is_string(...)));
    }

    public function setCategories(array $categories): void
    {
        $this->categories = [] === $categories ? null : array_values($categories);
    }

    protected function createTranslation(): FaqItemTranslationInterface
    {
        return new FaqItemTranslation();
    }
}
