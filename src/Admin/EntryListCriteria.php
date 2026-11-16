<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * What an entry list is filtered on. Each filter left out (null) lets every entry through
 */
final readonly class EntryListCriteria
{
    /**
     * @param string|null $search part of a title or a slug, in any locale, matched without regard to case
     * @param string|null $channel a channel code
     */
    public function __construct(
        public ?string $search = null,
        public ?EntryStatusFilter $status = null,
        public ?string $channel = null,
    ) {
    }
}
