<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * A reference a block tree or an entry's own fields hold, with where it is: in which block, in the layout or in the
 * entry, in which field and in which locale's value
 *
 * @internal
 */
final readonly class FoundReference
{
    /**
     * @param string|null $block the key of the block it's in, or null when it's in the layout or the entry
     * @param string|null $locale the locale of the translatable value it's in, or null for a shared value
     * @param bool $inEntry whether it's in one of the content type's own fields, on the entry or a translation
     */
    public function __construct(
        public Reference $reference,
        public ?string $block,
        public string $field,
        public ?string $locale,
        public bool $inEntry = false,
    ) {
    }
}
