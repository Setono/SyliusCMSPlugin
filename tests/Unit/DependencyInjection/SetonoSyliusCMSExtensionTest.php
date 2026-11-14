<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\DependencyInjection\SetonoSyliusCMSExtension;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusCMSExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusCMSExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_loads_the_service_definitions_with_an_empty_config(): void
    {
        $this->load();

        $resources = array_map(static fn (ResourceInterface $resource): string => (string) $resource, $this->container->getResources());

        self::assertContains(dirname(__DIR__, 3) . '/src/Resources/config/services.xml', $resources);
    }

    /**
     * @test
     */
    public function it_rejects_an_unknown_option(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "unknown" under "setono_sylius_cms"');

        $this->load(['unknown' => true]);
    }

    /**
     * @test
     */
    public function it_turns_on_the_doctrine_mapping_of_the_model_namespace(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.driver.doctrine/orm', true);
    }

    /**
     * @test
     */
    public function it_autoconfigures_field_type_handlers(): void
    {
        $this->load();

        $instanceof = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(FieldHandlerInterface::class, $instanceof);
        self::assertTrue($instanceof[FieldHandlerInterface::class]->hasTag('setono_sylius_cms.field_handler'));
    }

    /**
     * @test
     */
    public function it_autoconfigures_block_types_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(BlockType::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[BlockType::class]($definition, new BlockType('plain', 'app.plain', 'plain.html.twig'), new \ReflectionClass(Plain::class));

        self::assertTrue($definition->hasTag('setono_sylius_cms.block_type'));
    }

    /**
     * @test
     */
    public function it_registers_page_as_a_sylius_resource(): void
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusCMSExtension())->prepend($container);

        self::assertSame([[
            'resources' => [
                'setono_sylius_cms.page' => [
                    'driver' => 'doctrine/orm',
                    'classes' => ['model' => Page::class, 'interface' => PageInterface::class],
                    'translation' => ['classes' => ['model' => PageTranslation::class, 'interface' => PageTranslationInterface::class]],
                ],
            ],
        ]], $container->getExtensionConfig('sylius_resource'));
    }
}
