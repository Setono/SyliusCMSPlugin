<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Picks a block's block style, or takes it away
 */
final readonly class SetStyle implements Change
{
    public const OP = 'set_style';

    /**
     * @param string $key the block's key
     * @param string|null $style the code of one of the block type's styles, or null for none
     */
    public function __construct(
        public string $key,
        public ?string $style,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject(['op' => self::OP, 'key' => $this->key, 'style' => $this->style]);
    }
}
