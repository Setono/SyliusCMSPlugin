<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

trait InternalDescriptionAwareFixtureTrait
{
    protected function configureInternalDescriptionResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('internalDescription')->cannotBeEmpty();
    }
}
