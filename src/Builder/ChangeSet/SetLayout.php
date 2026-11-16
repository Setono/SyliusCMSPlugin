<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Tree\StoredLayout;

/**
 * Switches the tree's layout: the layout as the builder made it, with the values that carried over from the layout before
 * and their translation state. Undo switches back with the values the switch dropped.
 * See "Picking and switching" in docs/spec/layouts-and-presets.md
 */
final readonly class SetLayout implements Change
{
    public function __construct(public StoredLayout $layout)
    {
    }
}
