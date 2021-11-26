<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/* not final */ class CarouselFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_sylius_cms_carousel';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $child = $resourceNode->children();
        $child->scalarNode('code')->cannotBeEmpty();
        $child->variableNode('configuration')->cannotBeEmpty()->defaultValue([]);
        $child->variableNode('carouselBlocks')->cannotBeEmpty()->defaultValue([]);
    }
}
