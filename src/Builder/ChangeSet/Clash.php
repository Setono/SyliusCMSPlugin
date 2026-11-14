<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * A real clash between a save and what colleagues saved since the builder's lock token, which the editor resolves by
 * keeping theirs or taking the other. See "Saving" in docs/spec/publishing.md
 */
interface Clash
{
    /**
     * @return array<string, mixed> what the builder gets, with the clash's kind under "kind"
     */
    public function toArray(): array;
}
