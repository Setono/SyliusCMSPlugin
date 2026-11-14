<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A record a field value refers to. See "References" in docs/spec/content-model.md
 */
final readonly class Reference
{
    /**
     * @param string $type product, taxon, channel, customer_group, locale, product_attribute, entry or media_item
     * @param string $id a code or a UUID
     * @param bool $dependency true when an export must bring the record along
     */
    public function __construct(
        public string $type,
        public string $id,
        public bool $dependency,
    ) {
    }
}
