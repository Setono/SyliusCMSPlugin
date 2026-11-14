<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

/**
 * What the builder's preview shows: the edited entry, in a channel and a locale. Shown to, Date, the sample object
 * and the placement join it with their tickets. See "Preview mode" in docs/spec/builder.md
 */
final readonly class Preview
{
    public function __construct(
        public string $entryUuid,
        public string $channelCode,
        public string $localeCode,
    ) {
    }
}
