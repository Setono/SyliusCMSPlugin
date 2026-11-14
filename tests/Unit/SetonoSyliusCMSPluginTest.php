<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\SetonoSyliusCMSPlugin;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusCMSPluginTest extends TestCase
{
    /**
     * @test
     */
    public function it_supports_the_doctrine_orm(): void
    {
        self::assertSame(['doctrine/orm'], (new SetonoSyliusCMSPlugin())->getSupportedDrivers());
    }

    /**
     * @test
     */
    public function it_registers_its_compiler_passes(): void
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusCMSPlugin())->build($container);

        $passes = array_map(static fn (CompilerPassInterface $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(RegisterBlockTypesPass::class, $passes);
        self::assertContains(RegisterContentTypesPass::class, $passes);
        self::assertContains(DoctrineOrmMappingsPass::class, $passes, 'The resource bundle registers the Doctrine mapping of the Model namespace');
    }
}
