<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\ContentType;

/**
 * The FAQ's categories, the category content type of the FAQ: a title per translation and nothing else, so no URL, no
 * block tree and no fields of their own, ordered by the position editors drag them into in their admin list. Their
 * translations are EntryTranslation itself. See "Shipped content types" in docs/spec/collections.md
 */
#[ContentType(
    code: self::CONTENT_TYPE,
    label: 'setono_sylius_cms.content_type.faq_category',
    order: ContentType::ORDER_BY_POSITION,
)]
class FaqCategory extends Entry implements FaqCategoryInterface
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
