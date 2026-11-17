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
     * The block shows nothing
     *
     * @throws \Throwable the exception, in dev
     */
    public function blockFailed(BlockView $view, \Throwable $exception): void;

    /**
     * The placement's snippet shows nothing in its slot, such as when its tree can't be read or built
     *
     * @throws \Throwable the exception, in dev
     */
    public function snippetFailed(PlacementData $placement, \Throwable $exception): void;
}
