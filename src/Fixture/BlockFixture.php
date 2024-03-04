<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/* not final */ class BlockFixture extends AbstractResourceFixture
{
    use InternalDescriptionAwareFixtureTrait;

    public function getName(): string
    {
        return 'setono_sylius_cms_block';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('code')->cannotBeEmpty();
        $child->variableNode('content')->cannotBeEmpty()->defaultValue([]);
        $child->variableNode('translations')->cannotBeEmpty()->defaultValue([]);

        $this->configureInternalDescriptionResourceNode($resourceNode);
    }
}
