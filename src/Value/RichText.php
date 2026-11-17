<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Value;

/**
 * What a template gets for a rich text field: the document, sanitised against the rich text schema, and its HTML, in
 * which a link that doesn't resolve is plain text. cms_field() prints the HTML
 */
final readonly class RichText
{
    /**
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     * @param int $unresolvedLinks how many of its links don't resolve for the shopper, each counted once, so the builder's
     *                             preview can say why they show as plain text
     */
    public function __construct(
        public array $document,
        public string $html,
        public int $unresolvedLinks = 0,
    ) {
    }
}
