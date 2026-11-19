<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * A record as the Transfer screen's export lists it: an entry, an editor preset or a media item, named in the admin
 * user's locale. See "Transfer" in docs/spec/admin.md
 */
final readonly class ExportChoice
{
    /**
     * @param string $title an entry's title, or a preset's or a media item's name
     * @param string|null $detail what tells it apart: an entry's content type label (a translation key), a preset's
     *                            category or a media item's original filename
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public ?string $detail = null,
    ) {
    }
}
