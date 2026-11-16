<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

final readonly class RemoveBlock implements Change
{
    public const OP = 'remove_block';

    public function __construct(public string $key)
    {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject(['op' => self::OP, 'key' => $this->key]);
    }
}
