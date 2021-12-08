<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/* not final */ class AssetFixture extends AbstractResourceFixture
{
    use InternalDescriptionAwareFixtureTrait;

    public function getName(): string
    {
        return 'setono_sylius_cms_asset';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('name')->cannotBeEmpty();
        $child->scalarNode('path')->cannotBeEmpty();

        $this->configureInternalDescriptionResourceNode($resourceNode);
    }
}
