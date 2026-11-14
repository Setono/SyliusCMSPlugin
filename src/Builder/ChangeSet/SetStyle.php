<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Picks a block's block style, or takes it away
 */
final readonly class SetStyle implements Change
{
    /**
     * @param string $key the block's key
     * @param string|null $style the code of one of the block type's styles, or null for none
     */
    public function __construct(
        public string $key,
        public ?string $style,
    ) {
    }
}
