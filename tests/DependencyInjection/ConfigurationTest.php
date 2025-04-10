<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Form\Type\AssetType;
use Setono\SyliusCMSPlugin\Form\Type\BlockTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\BlockType;
use Setono\SyliusCMSPlugin\Form\Type\CarouselType;
use Setono\SyliusCMSPlugin\Form\Type\PageTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\PageType;
use Setono\SyliusCMSPlugin\Form\Type\TemplateType;
use Setono\SyliusCMSPlugin\Model\Asset;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\BlockTranslation;
use Setono\SyliusCMSPlugin\Model\Carousel;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Slide;
use Setono\SyliusCMSPlugin\Model\Template;
use Setono\SyliusCMSPlugin\Repository\AssetRepository;
use Setono\SyliusCMSPlugin\Repository\BlockRepository;
use Setono\SyliusCMSPlugin\Repository\CarouselRepository;
use Setono\SyliusCMSPlugin\Repository\PageRepository;
use Setono\SyliusCMSPlugin\Repository\TemplateRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Form\Type\DefaultResourceType;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Factory\TranslatableFactory;

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
    public function processed_value_contains_required_value(): void
    {
        $this->assertProcessedConfigurationEquals([], [
            'routing' => [
                'pages_path_prefix' => 'pages',
            ],
            'templates' => [],
            'resources' => [
                'asset' => [
                    'classes' => [
                        'model' => Asset::class,
                        'controller' => ResourceController::class,
                        'repository' => AssetRepository::class,
                        'form' => AssetType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'block' => [
                    'classes' => [
                        'model' => Block::class,
                        'controller' => ResourceController::class,
                        'repository' => BlockRepository::class,
                        'form' => BlockType::class,
                        'factory' => Factory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => BlockTranslation::class,
                            'controller' => ResourceController::class,
                            'form' => BlockTranslationType::class,
                            'factory' => TranslatableFactory::class,
                        ],
                    ],
                ],
                'page' => [
                    'classes' => [
                        'model' => Page::class,
                        'controller' => ResourceController::class,
                        'repository' => PageRepository::class,
                        'form' => PageType::class,
                        'factory' => Factory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => PageTranslation::class,
                            'controller' => ResourceController::class,
                            'form' => PageTranslationType::class,
                            'factory' => TranslatableFactory::class,
                        ],
                    ],
                ],
                'template' => [
                    'classes' => [
                        'model' => Template::class,
                        'controller' => ResourceController::class,
                        'repository' => TemplateRepository::class,
                        'form' => TemplateType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'carousel' => [
                    'classes' => [
                        'model' => Carousel::class,
                        'controller' => ResourceController::class,
                        'form' => CarouselType::class,
                        'factory' => Factory::class,
                        'repository' => CarouselRepository::class,
                    ],
                ],
                'slide' => [
                    'classes' => [
                        'model' => Slide::class,
                        'controller' => ResourceController::class,
                        'form' => DefaultResourceType::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ]);
    }
}
