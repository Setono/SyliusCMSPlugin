<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * A named, reusable block tree without its own URL, shown in slots through its placements, which aren't part of it
 * (ADR 0005). See "Snippets and their context" and "Placements" in docs/spec/slots.md
 */
interface SnippetInterface extends EntryInterface
{
    /** The code of its content type */
    public const CONTENT_TYPE = 'snippet';

    /**
     * The host-page context it needs, as context keys, such as ['product'] for product pages, or [] for any page. It can
     * be placed only in a slot that provides at least these keys
     *
     * @return list<string>
     */
    public function getContext(): array;

    /**
     * @param list<string> $context context keys
     */
    public function setContext(array $context): void;
}
