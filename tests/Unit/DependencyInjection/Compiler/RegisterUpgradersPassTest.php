<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterUpgradersPass;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\ArticleFromOne;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\DuplicateTeaserFromTwo;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\LandingFromOne;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\NotAnUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\OfAPlainClass;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\RenameField;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\TeaserFromOne;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\TeaserFromThree;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\TeaserFromTwo;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\TeaserFromZero;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RegisterUpgradersPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(StoredDataUpgrader::class, new Definition(StoredDataUpgrader::class, [null, null, null, null]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterUpgradersPass());
    }

    /**
     * @test
     */
    public function it_hands_the_upgraders_to_the_upgrade_service_by_the_block_type_layout_or_content_type_and_version_they_upgrade_from(): void
    {
        $this->setDefinition('app.teaser_upgrader', (new Definition(TeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(LandingFromOne::class, (new Definition(LandingFromOne::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setParameter('app.article_upgrader.class', ArticleFromOne::class);
        $this->setDefinition('app.article_upgrader', (new Definition('%app.article_upgrader.class%'))->addTag('setono_sylius_cms.upgrader'));

        $this->compile();

        self::assertEquals([
            'block_type:teaser:2' => new ServiceClosureArgument(new Reference('app.teaser_upgrader')),
            'layout:page:landing:1' => new ServiceClosureArgument(new Reference(LandingFromOne::class)),
            'content_type:article:1' => new ServiceClosureArgument(new Reference('app.article_upgrader')),
        ], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_accepts_one_upgrader_registered_twice(): void
    {
        $this->setDefinition('app.teaser_upgrader', (new Definition(TeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(TeaserFromTwo::class, (new Definition(TeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(LandingFromOne::class, (new Definition(LandingFromOne::class))->addTag('setono_sylius_cms.upgrader'));

        $this->compile();

        self::assertEquals([
            'block_type:teaser:2' => new ServiceClosureArgument(new Reference('app.teaser_upgrader')),
            'layout:page:landing:1' => new ServiceClosureArgument(new Reference(LandingFromOne::class)),
        ], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function two_upgraders_of_the_same_step_fail_the_build(): void
    {
        $this->setDefinition('app.teaser_upgrader', (new Definition(TeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition('app.other_teaser_upgrader', (new Definition(DuplicateTeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('Both %s and %s upgrade the block type "teaser" from version 2. Each step has one upgrader', TeaserFromTwo::class, DuplicateTeaserFromTwo::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function an_upgrader_must_upgrade_from_a_version_below_the_codes(): void
    {
        $this->setDefinition('app.teaser_upgrader', (new Definition(TeaserFromThree::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The upgrader %s upgrades the block type "teaser" from version 3, but it must upgrade from a version from 1 to 2, below the block type\'s version 3. Bump the version when you add an upgrader', TeaserFromThree::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function an_upgrader_must_upgrade_from_version_1_or_more(): void
    {
        $this->setDefinition('app.teaser_upgrader', (new Definition(TeaserFromZero::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('from version 0, but it must upgrade from a version from 1 to 2');

        $this->compile();
    }

    /**
     * @test
     */
    public function an_upgrader_must_upgrade_a_block_type_layout_or_content_type(): void
    {
        $this->setDefinition('app.upgrader', (new Definition(OfAPlainClass::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The upgrader %s upgrades stdClass, which is no block type, layout or content type class', OfAPlainClass::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function an_upgrader_must_implement_the_upgrader_interface(): void
    {
        $this->setDefinition('app.upgrader', (new Definition(NotAnUpgrader::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The upgrader %s must implement %s', NotAnUpgrader::class, UpgraderInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_tagged_class_must_carry_the_attribute(): void
    {
        $this->setDefinition('app.upgrader', (new Definition(RenameField::class))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.upgrader" is tagged "setono_sylius_cms.upgrader", but its class %s has no #[AsUpgrader]', RenameField::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_tagged_service_must_have_an_existing_class(): void
    {
        $this->setDefinition('app.missing', (new Definition('App\Missing'))->addTag('setono_sylius_cms.upgrader'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The upgrader service "app.missing" must have an existing class');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_hands_an_empty_locator_to_the_upgrade_service_without_upgraders(): void
    {
        $this->compile();

        self::assertSame([], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_upgrade_service(): void
    {
        $this->container->removeDefinition(StoredDataUpgrader::class);
        $this->setDefinition('app.upgrader', (new Definition(OfAPlainClass::class))->addTag('setono_sylius_cms.upgrader'));

        $this->compile();

        $this->assertContainerBuilderHasService('app.upgrader');
    }

    /**
     * @test
     */
    public function it_accepts_a_version_reached_through_an_upgrader_for_every_step_from_1(): void
    {
        $this->registerSubjects();
        $this->setDefinition(TeaserFromOne::class, (new Definition(TeaserFromOne::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(TeaserFromTwo::class, (new Definition(TeaserFromTwo::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(LandingFromOne::class, (new Definition(LandingFromOne::class))->addTag('setono_sylius_cms.upgrader'));
        $this->setDefinition(ArticleFromOne::class, (new Definition(ArticleFromOne::class))->addTag('setono_sylius_cms.upgrader'));

        $this->compile();

        self::assertCount(4, $this->locatorArgument());
    }

    /**
     * @test
     *
     * @dataProvider provideMissingSteps
     *
     * @param list<class-string> $upgraders
     */
    public function a_version_bump_without_an_upgrader_for_every_step_from_1_fails_the_build(array $upgraders, string $message): void
    {
        $this->registerSubjects();
        foreach ($upgraders as $upgrader) {
            $this->setDefinition($upgrader, (new Definition($upgrader))->addTag('setono_sylius_cms.upgrader'));
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->compile();
    }

    /**
     * @return iterable<string, array{list<class-string>, string}>
     */
    public static function provideMissingSteps(): iterable
    {
        yield 'a block type' => [
            [TeaserFromTwo::class, LandingFromOne::class, ArticleFromOne::class],
            'The block type "teaser" is at version 3, but no upgrader upgrades it from version 1. Each version bump needs an upgrader from the version before it',
        ];
        yield 'a layout' => [
            [TeaserFromOne::class, TeaserFromTwo::class, ArticleFromOne::class],
            'The layout "landing" is at version 2, but no upgrader upgrades it from version 1',
        ];
        yield 'a content type' => [
            [TeaserFromOne::class, TeaserFromTwo::class, LandingFromOne::class],
            'The content type "article" is at version 2, but no upgrader upgrades it from version 1',
        ];
    }

    /**
     * Registers the Teaser block type at version 3, the Heading block type at version 1, the Landing layout at version 2
     * and the Article content type at version 2, as the other passes do
     */
    private function registerSubjects(): void
    {
        $this->setDefinition(BlockTypeRegistry::class, new Definition(BlockTypeRegistry::class, [['teaser' => Teaser::class, 'heading' => Heading::class]]));
        $this->setDefinition(LayoutRegistry::class, new Definition(LayoutRegistry::class, [['page' => ['landing' => Landing::class]]]));
        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[
            'article' => ['model' => Article::class, 'translation' => null, 'resource' => 'app.article'],
        ]]));
    }

    /**
     * @return array<array-key, mixed> the upgraders the locator passed to the upgrade service holds
     */
    private function locatorArgument(): array
    {
        $argument = $this->container->getDefinition(StoredDataUpgrader::class)->getArgument(3);
        self::assertInstanceOf(Reference::class, $argument);

        $locator = $this->container->getDefinition((string) $argument);
        self::assertSame(ServiceLocator::class, $locator->getClass());

        $upgraders = $locator->getArgument(0);
        self::assertIsArray($upgraders);

        return $upgraders;
    }
}
