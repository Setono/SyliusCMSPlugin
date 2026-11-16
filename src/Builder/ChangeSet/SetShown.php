<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Switches a translation's "Show in <language>" on or off: off holds it back. See "Held-back translations" in
 * docs/spec/publishing.md
 */
final readonly class SetShown implements Change
{
    public const OP = 'set_shown';

    public function __construct(
        public string $locale,
        public bool $shown,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject(['op' => self::OP, 'locale' => $this->locale, 'shown' => $this->shown]);
    }
}
