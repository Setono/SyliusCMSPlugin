<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * An error or a warning the admin flags: on the entry's overview page, by the lists' problem marker, in the Content
 * badge, and in setono:cms:lint for the kinds it reports. See "Problems" in docs/spec/admin.md
 */
final readonly class Problem
{
    /**
     * @param string $kind the code of its kind, such as "unknown_block_type"
     * @param EntryInterface $entry the record it's on
     * @param TranslatableInterface $message what's wrong, to translate where it's shown
     * @param Fix|null $fix where it's fixed, or null when there's no one place
     * @param string|null $block the key of the block it's in, or null when it isn't in a block
     * @param string|null $field the field it's in, or null
     * @param string|null $locale the locale of the value it's in, or null
     */
    public function __construct(
        public string $kind,
        public Severity $severity,
        public EntryInterface $entry,
        public TranslatableInterface $message,
        public ?Fix $fix = null,
        public ?string $block = null,
        public ?string $field = null,
        public ?string $locale = null,
    ) {
    }
}
