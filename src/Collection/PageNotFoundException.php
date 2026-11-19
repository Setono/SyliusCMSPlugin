<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

/**
 * The page a URL asks for with ?page=N doesn't exist: it's past the last page of the entry's paginated Entries block,
 * or the entry has none that shows, so it has only page 1. The shop answers 404. See "Pagination" in
 * docs/spec/collections.md
 */
final class PageNotFoundException extends \RuntimeException
{
}
