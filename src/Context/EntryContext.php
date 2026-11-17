<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;

/**
 * The host-page context an entry provides its own tree: an entry the CMS renders provides itself, as entry, to its tree
 * and to its layout's slots, and a snippet the context it declares, which the slot it shows in provides. The builder
 * offers, and publish allows, only what needs no more than that. See "Context keys" and "Snippets and their context" in
 * docs/spec/slots.md
 */
final class EntryContext
{
    /** The context key of an entry the CMS renders */
    public const ENTRY = 'entry';

    private function __construct()
    {
    }

    /**
     * The context keys the entry provides its tree
     *
     * @return list<string>
     */
    public static function keys(EntryInterface $entry): array
    {
        return $entry instanceof SnippetInterface ? $entry->getContext() : [self::ENTRY];
    }

    /**
     * The context keys an entry of the content type provides its tree, or null when each entry declares its own, as a
     * snippet does
     *
     * @return list<string>|null
     */
    public static function keysOf(ContentTypeMetadata $contentType): ?array
    {
        return is_a($contentType->modelClass, SnippetInterface::class, true) ? null : [self::ENTRY];
    }

    /**
     * The host-page context the entry renders its own tree with: itself, as entry. A snippet's comes from the slot it
     * shows in
     *
     * @return array<string, object>
     */
    public static function objects(EntryInterface $entry): array
    {
        return $entry instanceof SnippetInterface ? [] : [self::ENTRY => $entry];
    }
}
