<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * A media folder as the Transfer screen's export lists it, under its parent. See "Transfer" in docs/spec/admin.md
 */
final readonly class ExportFolderChoice
{
    /**
     * @param int $depth 0 for a folder at the top, 1 for one inside it, and so on
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public int $depth,
    ) {
    }
}
