<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Switches the tree's layout: the layout as the builder made it, with the values that carried over from the layout before
 * and their translation state. Undo switches back with the values the switch dropped.
 * See "Picking and switching" in docs/spec/layouts-and-presets.md
 */
final readonly class SetLayout implements Change
{
    public const OP = 'set_layout';

    public function __construct(public StoredLayout $layout)
    {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject(['op' => self::OP, 'layout' => $serializer->normalizeLayout($this->layout)]);
    }
}
