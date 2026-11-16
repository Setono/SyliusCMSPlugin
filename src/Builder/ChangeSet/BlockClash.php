<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * A clash over a whole block: the editor changed a block a colleague deleted, or deleted a block a colleague changed
 * something in
 */
final readonly class BlockClash implements Clash
{
    /**
     * The editor edited the block, moved it or put blocks into it, and a colleague deleted it. Their changes to it
     * aren't applied
     */
    public const DELETED = 'deleted_block';

    /**
     * The editor deleted the block, and a colleague changed a value in it, added a block to it or moved one into it.
     * The block isn't deleted
     */
    public const CHANGED = 'changed_block';

    /**
     * @param self::DELETED|self::CHANGED $kind
     */
    private function __construct(
        public string $kind,
        public string $key,
    ) {
    }

    public static function deleted(string $key): self
    {
        return new self(self::DELETED, $key);
    }

    public static function changed(string $key): self
    {
        return new self(self::CHANGED, $key);
    }

    public function toArray(): array
    {
        return ['kind' => $this->kind, 'key' => $this->key];
    }
}
