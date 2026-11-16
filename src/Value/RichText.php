<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Value;

/**
 * What a template gets for a rich text field: the document, sanitised against the rich text schema, and its HTML.
 * cms_field() prints the HTML
 */
final readonly class RichText
{
    /**
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     */
    public function __construct(
        public array $document,
        public string $html,
    ) {
    }
}
