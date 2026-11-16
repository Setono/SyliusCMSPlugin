<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Sets a value of one of the layout's fields. It names the layout it was made in, so a value made in a layout a colleague
 * has switched away from since isn't set in the new one
 */
final readonly class SetLayoutValue implements Change
{
    /**
     * @param string $layout the code of the layout the value was set in
     * @param string|null $locale the locale for a translatable field, null for a shared one
     * @param mixed $value null removes the value
     */
    public function __construct(
        public string $layout,
        public string $field,
        public ?string $locale,
        public mixed $value,
    ) {
    }
}
