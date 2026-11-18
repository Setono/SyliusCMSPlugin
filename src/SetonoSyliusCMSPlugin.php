<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin;

use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\CheckFieldTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBindablePropertiesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterConditionKindsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContextResolversPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLayoutsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLinkTargetsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterProblemKindsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterSourcesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterUpgradersPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusCMSPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    /**
     * @return list<string>
     */
    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterBlockTypesPass());
        $container->addCompilerPass(new RegisterContentTypesPass());
        $container->addCompilerPass(new RegisterLayoutsPass());
        $container->addCompilerPass(new RegisterUpgradersPass());
        $container->addCompilerPass(new RegisterProblemKindsPass());
        $container->addCompilerPass(new RegisterConditionKindsPass());
        $container->addCompilerPass(new RegisterLinkTargetsPass());
        $container->addCompilerPass(new RegisterSourcesPass());
        $container->addCompilerPass(new RegisterContextResolversPass());
        $container->addCompilerPass(new RegisterBindablePropertiesPass());
        // After the passes above, which register the classes whose fields it checks
        $container->addCompilerPass(new CheckFieldTypesPass());
    }
}
