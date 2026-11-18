<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The placement resource's factory: it creates placements of the configured model, so a project that overrides the model
 * gets placements of its own class. The model extends Placement, whose Doctrine mapping it inherits. Change a
 * snippet's placements through PlacementEditorInterface
 *
 * @extends FactoryInterface<PlacementInterface>
 */
interface PlacementFactoryInterface extends FactoryInterface
{
    /**
     * A placement of the snippet in the slot with this identifier, at this position among the slot's placements
     */
    public function createFor(SnippetInterface $snippet, string $slot, int $position): PlacementInterface;
}
