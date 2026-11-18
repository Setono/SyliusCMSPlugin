<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Adds the CMS metadata to a content type: a Doctrine entity that extends Entry and is registered as a Sylius resource.
 * A subclass of a content type's model keeps it: it's read from the nearest class in the hierarchy that has one.
 * See "Content types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentType
{
    /** The order that lets editors drag entries into order in the admin list */
    public const ORDER_BY_POSITION = 'position';

    /**
     * @param string $code the discriminator value and the column prefix; never changes
     * @param string $label a translation key
     * @param int $version versions its fields in drafts, revisions and exports
     * @param string|null $prefix the URL prefix: null means no URL of its own, and '' means URLs at the root
     * @param list<Allow>|null $blocks what the top level of its tree may hold: null means it has no block tree
     * @param list<Card> $cards how its entries look listed among others, one per display
     * @param array<string, 'ASC'|'DESC'>|'position' $order the order sources list its entries in, by property, or
     *                                                      'position', which editors drag entries into in the admin list
     * @param class-string|null $categoryType the category content type of its collection
     * @param string|null $descriptionField the property of its translations the meta description falls back to, such as
     *                                      a blog post's excerpt. See "SEO fields" in docs/spec/urls-and-seo.md
     */
    public function __construct(
        public string $code,
        public string $label,
        public int $version = 1,
        public ?string $prefix = null,
        public ?array $blocks = null,
        public array $cards = [],
        public array|string $order = ['createdAt' => 'DESC'],
        public ?string $categoryType = null,
        public ?string $descriptionField = null,
    ) {
    }
}
