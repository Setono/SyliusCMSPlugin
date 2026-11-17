<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Registry\UnknownSlotException;

/**
 * Renders a slot where its template event fires or where a template calls cms_slot(). See "Rendering a slot" in
 * docs/spec/rendering.md
 */
interface SlotRendererInterface
{
    /**
     * The slot's HTML: what its placements show, in its wrapper, or nothing at all when they show nothing
     *
     * @param array<array-key, mixed> $context what the template event or the cms_slot() call passes. The slot takes the
     *                                         context keys it provides from it
     *
     * @throws UnknownSlotException when the code doesn't declare the slot
     */
    public function render(string $identifier, array $context = []): string;
}
