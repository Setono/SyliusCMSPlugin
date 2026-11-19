<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source;

/**
 * An entry source that can say which content type it lists, so the Entries block offers that content type's card
 * displays. Without it, or when it can't tell, the listed content type is the one its #[AsSource(of:)] names, and when
 * that's any entry, the block offers every display. See "Sources" in docs/spec/extending.md
 */
interface ListedContentTypeAwareInterface
{
    /**
     * The code of the content type it lists with these parameters, in an entry of $hostContentType (null outside an
     * entry). Null when it can't tell
     *
     * @param object $params its parameters, hydrated
     */
    public function getListedContentType(object $params, ?string $hostContentType): ?string;
}
