<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * How an entry of a content type looks when it's listed among others in one display, such as a grid. A content type
 * names its cards in #[ContentType(cards:)]. See "Cards" in docs/spec/collections.md
 */
final readonly class Card
{
    /**
     * @param string $display grid, carousel, accordion, or a project's own
     * @param string $template the card's template, which gets `entry` and `display`
     */
    public function __construct(
        public string $display,
        public string $template,
    ) {
    }
}
