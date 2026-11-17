<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * A reference a block tree holds, with where it is: in which block, or in the layout, in which field and in which
 * locale's value
 *
 * @internal
 */
final readonly class FoundReference
{
    /**
     * @param string|null $block the key of the block it's in, or null when it's in the layout
     * @param string|null $locale the locale of the translatable value it's in, or null for a shared value
     */
    public function __construct(
        public Reference $reference,
        public ?string $block,
        public string $field,
        public ?string $locale,
    ) {
    }
}
