<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Webmozart\Assert\Assert;

final class RegisterPreviewersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('setono_sylius_cms.previewer.composite')) {
            return;
        }

        $checker = $container->getDefinition('setono_sylius_cms.previewer.composite');

        /**
         * @var string $id
         */
        foreach ($container->findTaggedServiceIds('setono_sylius_cms.previewer') as $id => $tags) {
            /** @var mixed $tag */
            foreach ($tags as $tag) {
                Assert::isArray($tag);

                /** @var mixed $priority */
                $priority = $tag['priority'] ?? 0;
                Assert::integer($priority);

                $checker->addMethodCall('add', [new Reference($id), $priority]);
            }
        }
    }
}
