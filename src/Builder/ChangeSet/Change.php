<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * One change the builder made to the working copy since it last loaded it. Each kind has its op, OP, which
 * ChangeSet::fromArray() reads it by
 */
interface Change
{
    /**
     * The change in the format ChangeSet::fromArray() reads, with its op, to encode as canonical JSON
     */
    public function toArray(StoredTreeSerializer $serializer): ListedObject;
}
