<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source;

/**
 * A source that needs the entry context and fits only some content types as that entry, such as In this category,
 * which fits categories. The builder offers it only in entries of those content types, publish refuses it in others,
 * and a preset holding it is offered only there. See "Sources" in docs/spec/extending.md
 */
interface EntryContextAwareInterface
{
    /**
     * Whether it fits an entry of the content type, by its code, as the host page's entry
     */
    public function supportsEntryContentType(string $code): bool;
}
