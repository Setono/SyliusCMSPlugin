<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

final readonly class SetValue implements Change
{
    /**
     * @param string $key the block's key
     * @param string|null $locale the locale for a translatable field, null for a shared one
     * @param mixed $value null removes the value
     */
    public function __construct(
        public string $key,
        public string $field,
        public ?string $locale,
        public mixed $value,
    ) {
    }
}
