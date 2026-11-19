<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A reason the import refuses that overwriting doesn't fix: code the target lacks or is older than, an Embed code
 * block while it's off, or a publish that fails. See "Code the target must have" and "Commands" in
 * docs/spec/transfer.md
 */
final readonly class ImportRefusal
{
    /**
     * @param string|null $uuid the record it's about, if one
     * @param TranslatableInterface $reason why, naming the record, in the messages domain
     */
    public function __construct(
        public ?string $uuid,
        public TranslatableInterface $reason,
    ) {
    }
}
