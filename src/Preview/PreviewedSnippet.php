<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * The snippet a preview or share link shows, with the placement it shows it through
 *
 * @internal
 */
final readonly class PreviewedSnippet
{
    /**
     * @param SnippetInterface $snippet the content it shows: the snippet's working copy, or a revision's
     * @param PlacementInterface|null $placement the snippet's placement the token names, or null for a plain page, or
     *                                           for one the snippet no longer has
     */
    public function __construct(
        public Preview $preview,
        public SnippetInterface $snippet,
        public ?PlacementInterface $placement,
    ) {
    }
}
