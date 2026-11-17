<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Where the shop reads placements from. The slot renderer asks once a request, when the first slot on the page renders,
 * and every later slot reuses the answer. A project caches placements by decorating it, and invalidates the cache on
 * StorefrontChangedInterface events, such as PlacementsChanged. See "Rendering services" in docs/spec/extending.md
 */
interface PlacementProviderInterface
{
    /**
     * All of the channel's placements: those of the snippets in the channel, whether they're visible or not, in slot and
     * position order
     *
     * @return list<PlacementData>
     */
    public function getPlacements(ChannelInterface $channel): array;
}
