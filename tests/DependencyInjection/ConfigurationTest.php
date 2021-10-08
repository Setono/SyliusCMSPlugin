<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Controller\Sylius\ViewController;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Doctrine\ORM\BlockRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\PageRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\TemplateRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\ViewRepository;
use Setono\SyliusCMSPlugin\Form\Type\BlockTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\BlockType;
use Setono\SyliusCMSPlugin\Form\Type\PageTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\PageType;
use Setono\SyliusCMSPlugin\Form\Type\TemplateType;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\BlockTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Template;
use Setono\SyliusCMSPlugin\Model\View;
use Setono\SyliusCMSPlugin\Model\ViewBlock;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Form\Type\DefaultResourceType;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
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
            'driver' => SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            'templates' => [],
            'resources' => [
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
                'view' => [
                    'classes' => [
                        'model' => View::class,
                        'controller' => ViewController::class,
                        'repository' => ViewRepository::class,
                        'form' => DefaultResourceType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'view_block' => [
                    'classes' => [
                        'model' => ViewBlock::class,
                        'controller' => ResourceController::class,
                        'form' => DefaultResourceType::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ]);
    }
}
