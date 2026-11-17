<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * What happens when a block, or a snippet in a slot, throws while it renders for the shop: in production it drops out
 * and is logged, and in dev (kernel.debug on) the exception is thrown. The builder's preview shows a block's error on
 * the block instead (Preview\PreviewBlockRenderer). See "Failures" in docs/spec/rendering.md
 */
interface FailureHandlerInterface
{
    /**
     * The block shows nothing, nor do the blocks inside it, when it throws while its view is built
     *
     * @param string $key the block's key
     * @param string $type its block type's code
     * @param string|null $entry the UUID of the entry whose tree holds it, or null for blocks that are no entry's
     *
     * @throws \Throwable the exception, in dev
     */
    public function blockFailed(string $key, string $type, ?string $entry, \Throwable $exception): void;

    /**
     * The placement's snippet shows nothing in its slot, such as when its tree can't be read
     *
     * @throws \Throwable the exception, in dev
     */
    public function snippetFailed(PlacementData $placement, \Throwable $exception): void;
}
