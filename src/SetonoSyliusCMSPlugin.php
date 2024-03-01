<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\TagElementCacheInvalidatorListenerPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusCMSPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TagElementCacheInvalidatorListenerPass());

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_cms.previewer.composite',
            'setono_sylius_cms.previewer',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_cms.checker.eligibility.page.composite',
            'setono_sylius_cms.page_eligibility_checker',
        ));

        $container->addCompilerPass(new CompositeCompilerPass(
            'setono_sylius_cms.generator.twig.composite',
            'setono_sylius_cms.twig_generator',
        ));
    }
}
