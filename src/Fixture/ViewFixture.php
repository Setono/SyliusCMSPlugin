<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/* not final */ class ViewFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_sylius_cms_view';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('code')->cannotBeEmpty();
        $child->scalarNode('template')->cannotBeEmpty();
        $child->variableNode('viewBlocks')->cannotBeEmpty()->defaultValue([]);
    }
}
