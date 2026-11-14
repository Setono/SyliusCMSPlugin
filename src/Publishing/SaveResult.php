<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\Clash;

final readonly class SaveResult
{
    /**
     * @param LoadedWorkingCopy $saved the working copy after the save, with the lock token the builder's next save carries
     * @param bool $merged whether colleagues saved since the builder's lock token, so the builder pulls their changes in
     * @param list<Clash> $clashes the editor's changes that clashed with theirs and weren't saved
     */
    public function __construct(
        public LoadedWorkingCopy $saved,
        public bool $merged,
        public array $clashes = [],
    ) {
    }
}
