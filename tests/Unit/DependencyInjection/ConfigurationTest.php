<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyConfigTest
 */
final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function it_declares_page_as_a_resource_by_default(): void
    {
        $this->assertProcessedConfigurationEquals([
            [],
        ], [
            'resources' => [
                'page' => [
                    'classes' => [
                        'model' => Page::class,
                        'interface' => PageInterface::class,
                        'controller' => ResourceController::class,
                        'factory' => TranslatableFactory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => PageTranslation::class,
                            'interface' => PageTranslationInterface::class,
                            'controller' => ResourceController::class,
                            'factory' => Factory::class,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function a_project_extends_page_by_overriding_its_classes(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['page' => [
                'options' => ['foo' => 'bar'],
                'classes' => ['model' => 'App\Entity\Page', 'repository' => 'App\Repository\PageRepository'],
                'translation' => ['classes' => ['model' => 'App\Entity\PageTranslation']],
            ]]],
        ], [
            'resources' => [
                'page' => [
                    'options' => ['foo' => 'bar'],
                    'classes' => [
                        'model' => 'App\Entity\Page',
                        'repository' => 'App\Repository\PageRepository',
                        'interface' => PageInterface::class,
                        'controller' => ResourceController::class,
                        'factory' => TranslatableFactory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => 'App\Entity\PageTranslation',
                            'interface' => PageTranslationInterface::class,
                            'controller' => ResourceController::class,
                            'factory' => Factory::class,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     *
     * @dataProvider provideEmptyClasses
     *
     * @param array<string, mixed> $config
     */
    public function it_refuses_an_empty_class(array $config): void
    {
        $this->assertConfigurationIsInvalid([['resources' => ['page' => $config]]], 'cannot contain an empty value');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public function provideEmptyClasses(): iterable
    {
        foreach (['model', 'interface', 'controller', 'repository', 'factory'] as $class) {
            yield $class => [['classes' => [$class => '']]];
            yield 'translation ' . $class => [['translation' => ['classes' => [$class => '']]]];
        }
    }
}
