<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * One stored value of a field of a block or of the layout, as TreeValues walks them: a shared field's value, or a
 * translatable field's value in one locale
 *
 * @internal
 */
final readonly class StoredValue
{
    /**
     * @param string|null $locale the locale of a translatable field's value, or null for a shared field's, and for a
     *                            translatable field's that isn't a locale map, which doesn't fit
     * @param StoredBlock|null $block the block whose value it is, or null for the layout's
     * @param BlockTypeMetadata|null $blockType the block's type, or null for the layout's
     */
    public function __construct(
        public FieldMetadata $field,
        public mixed $value,
        public ?string $locale = null,
        public ?StoredBlock $block = null,
        public ?BlockTypeMetadata $blockType = null,
    ) {
    }

    /**
     * Whether it's a translatable field's value that isn't a locale map, which doesn't fit its field
     */
    public function isNotByLocale(): bool
    {
        return $this->field->translatable && null === $this->locale;
    }
}
