<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
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
    public function it_declares_page_and_the_draft_as_resources_by_default(): void
    {
        $this->assertProcessedConfigurationEquals([
            [],
        ], [
            'disabled' => ['block_types' => []],
            'assets' => true,
            'resources' => [
                'draft' => [
                    'classes' => [
                        'model' => Draft::class,
                        'interface' => DraftInterface::class,
                        'repository' => DraftRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
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
    public function a_project_switches_off_shipped_block_types_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['disabled' => ['block_types' => ['spacer']]],
            ['disabled' => ['block_types' => ['group']]],
        ], ['disabled' => ['block_types' => ['spacer', 'group']]], 'disabled');
    }

    /**
     * @test
     */
    public function a_project_stops_loading_the_shipped_stylesheet(): void
    {
        $this->assertProcessedConfigurationEquals([['assets' => false]], ['assets' => false], 'assets');
    }

    /**
     * @test
     */
    public function assets_is_a_boolean(): void
    {
        $this->assertConfigurationIsInvalid([['assets' => 'no']], 'Invalid type for path "setono_sylius_cms.assets"');
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
        ], 'resources.page');
    }

    /**
     * @test
     */
    public function a_project_overrides_the_draft_model(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['draft' => ['classes' => ['model' => 'App\Entity\Draft']]]],
        ], [
            'resources' => [
                'draft' => [
                    'classes' => [
                        'model' => 'App\Entity\Draft',
                        'interface' => DraftInterface::class,
                        'repository' => DraftRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ], 'resources.draft');
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
        $this->assertConfigurationIsInvalid([['resources' => $config]], 'cannot contain an empty value');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public function provideEmptyClasses(): iterable
    {
        foreach (['model', 'interface', 'controller', 'repository', 'factory'] as $class) {
            yield $class => [['page' => ['classes' => [$class => '']]]];
            yield 'translation ' . $class => [['page' => ['translation' => ['classes' => [$class => '']]]]];
        }

        foreach (['model', 'interface', 'repository', 'factory'] as $class) {
            yield 'draft ' . $class => [['draft' => ['classes' => [$class => '']]]];
        }
    }
}
