<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin;

use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterElementLoadersPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterPageEligibilityCheckersPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterPreviewersPass;
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

        $container->addCompilerPass(new RegisterElementLoadersPass());
        $container->addCompilerPass(new RegisterPageEligibilityCheckersPass());
        $container->addCompilerPass(new RegisterPreviewersPass());
    }
}
