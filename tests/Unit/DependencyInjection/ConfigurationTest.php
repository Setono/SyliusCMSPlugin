<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\EntryPresenceInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Model\SavedChangeSetInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepository;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
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
    public function it_declares_page_the_draft_and_the_rows_beside_an_entry_as_resources_by_default(): void
    {
        $this->assertProcessedConfigurationEquals([
            [],
        ], [
            'disabled' => ['block_types' => []],
            'assets' => true,
            'block_styles' => [],
            'resources' => [
                'draft' => [
                    'classes' => [
                        'model' => Draft::class,
                        'interface' => DraftInterface::class,
                        'repository' => DraftRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'entry_channel' => [
                    'classes' => [
                        'model' => EntryChannel::class,
                        'interface' => EntryChannelInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'entry_audience_group' => [
                    'classes' => [
                        'model' => EntryAudienceGroup::class,
                        'interface' => EntryAudienceGroupInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'entry_presence' => [
                    'classes' => [
                        'model' => EntryPresence::class,
                        'interface' => EntryPresenceInterface::class,
                        'repository' => EntryPresenceRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'saved_change_set' => [
                    'classes' => [
                        'model' => SavedChangeSet::class,
                        'interface' => SavedChangeSetInterface::class,
                        'repository' => SavedChangeSetRepository::class,
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
    public function a_project_adds_block_styles_to_any_block_type_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['block_styles' => ['banner' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-banner--brand']]]],
            ['block_styles' => [
                'banner' => ['outlined' => ['label' => 'app.cms.block_style.outlined', 'class' => 'app-banner--outlined']],
                'group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']],
            ]],
        ], ['block_styles' => [
            'banner' => [
                'brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-banner--brand'],
                'outlined' => ['label' => 'app.cms.block_style.outlined', 'class' => 'app-banner--outlined'],
            ],
            'group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']],
        ]], 'block_styles');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidBlockStyles
     *
     * @param array<array-key, mixed> $blockStyles
     */
    public function it_refuses_an_invalid_block_style(array $blockStyles, string $message): void
    {
        $this->assertConfigurationIsInvalid([['block_styles' => $blockStyles]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidBlockStyles(): iterable
    {
        yield 'no label' => [['group' => ['brand' => ['class' => 'app-group--brand']]], 'The child config "label" under "setono_sylius_cms.block_styles.group.brand" must be configured'];
        yield 'no class' => [['group' => ['brand' => ['label' => 'app.brand']]], 'The child config "class" under "setono_sylius_cms.block_styles.group.brand" must be configured'];
        yield 'an empty label' => [['group' => ['brand' => ['label' => '', 'class' => 'app-group--brand']]], 'cannot contain an empty value'];
        yield 'an empty class' => [['group' => ['brand' => ['label' => 'app.brand', 'class' => '']]], 'cannot contain an empty value'];
        yield 'a style code that is no code' => [['group' => ['Brand' => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block style code "Brand" must match ^[a-z][a-z0-9_]*$'];
        yield 'a block type code that is no code' => [['app-banner' => ['brand' => ['label' => 'app.brand', 'class' => 'app-banner--brand']]], 'The block type code "app-banner" must match ^[a-z][a-z0-9_]*$'];
        yield 'a style code with a hyphen' => [['group' => ['dark-blue' => ['label' => 'app.dark_blue', 'class' => 'app-group--dark-blue']]], 'The block style code "dark-blue" must match'];
        yield 'a numeric style code' => [['group' => [42 => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block style code "42" must match'];
        yield 'a numeric block type code' => [[42 => ['brand' => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block type code "42" must match'];
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
