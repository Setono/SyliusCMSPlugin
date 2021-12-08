<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/* not final */ class PageFixture extends AbstractResourceFixture
{
    use InternalDescriptionAwareFixtureTrait;

    public function getName(): string
    {
        return 'setono_sylius_cms_page';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('code')->cannotBeEmpty();
        $child->scalarNode('view')->cannotBeEmpty();
        $child->scalarNode('title')->cannotBeEmpty();
        $child->scalarNode('slug')->cannotBeEmpty();
        $child->scalarNode('metaDescription')->cannotBeEmpty();
        $child->variableNode('translations')->cannotBeEmpty()->defaultValue([]);
        $child->arrayNode('channels')->scalarPrototype();

        $this->configureInternalDescriptionResourceNode($resourceNode);
    }
}
