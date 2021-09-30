<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterPageEligibilityCheckersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('setono_sylius_cms.checker.eligibility.page.composite')) {
            return;
        }

        $checker = $container->getDefinition('setono_sylius_cms.checker.eligibility.page.composite');

        /** @var string $id */
        foreach (array_keys($container->findTaggedServiceIds('setono_sylius_cms.page_eligibility_checker')) as $id) {
            $checker->addMethodCall('add', [new Reference($id)]);
        }
    }
}
