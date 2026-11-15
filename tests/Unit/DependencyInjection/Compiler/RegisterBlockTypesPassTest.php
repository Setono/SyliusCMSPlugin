<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidStyleCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\NotABlockType;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SameStyleTwice;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterBlockTypesPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(BlockTypeRegistry::class, new Definition(BlockTypeRegistry::class, [[], new Reference(FieldReader::class)]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterBlockTypesPass());
    }

    /**
     * @test
     */
    public function it_hands_the_tagged_block_types_to_the_registry_by_code_and_removes_their_services(): void
    {
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));
        $this->setDefinition(Plain::class, (new Definition())->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 0, ['heading' => Heading::class, 'plain' => Plain::class]);
        $this->assertContainerBuilderNotHasService('app.heading');
        $this->assertContainerBuilderNotHasService(Plain::class);
    }

    /**
     * @test
     */
    public function it_resolves_a_class_given_as_a_parameter(): void
    {
        $this->setParameter('app.heading.class', Heading::class);
        $this->setDefinition('app.heading', (new Definition('%app.heading.class%'))->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 0, ['heading' => Heading::class]);
    }

    /**
     * @test
     */
    public function it_accepts_one_block_type_registered_twice(): void
    {
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));
        $this->setDefinition(Heading::class, (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 0, ['heading' => Heading::class]);
    }

    /**
     * @test
     */
    public function two_block_types_with_the_same_code_fail_the_build(): void
    {
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));
        $this->setDefinition('app.other_heading', (new Definition(SameCodeAsHeading::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The block type code "heading" is used by both %s and %s', Heading::class, SameCodeAsHeading::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_block_type_code_must_be_a_valid_code(): void
    {
        $this->setDefinition('app.invalid', (new Definition(InvalidCode::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Invalid-Code"');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_block_style_code_that_is_not_a_valid_code(): void
    {
        $this->setDefinition('app.invalid_style', (new Definition(InvalidStyleCode::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The block style code "Dark-Blue" of %s must match', InvalidStyleCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_accepts_a_block_type_whose_block_styles_have_valid_codes_of_their_own(): void
    {
        $this->setDefinition('app.group', (new Definition(Group::class))->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 0, ['group' => Group::class]);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_block_styles_of_a_block_type_with_the_same_code(): void
    {
        $this->setDefinition('app.same_style_twice', (new Definition(SameStyleTwice::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('%s declares the block style "dark" more than once', SameStyleTwice::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_block_styles_added_in_config_to_a_block_type_that_does_not_exist(): void
    {
        $this->setParameter('setono_sylius_cms.block_styles', [
            'heading' => ['outlined' => ['label' => 'app.outlined', 'class' => 'app-heading--outlined']],
            'app_banner' => ['brand' => ['label' => 'app.brand', 'class' => 'app-banner--brand']],
        ]);
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setono_sylius_cms.block_styles adds styles to the block type "app_banner", but there is no block type with that code');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_accepts_block_styles_added_in_config_to_existing_block_types(): void
    {
        $this->setParameter('setono_sylius_cms.block_styles', ['heading' => ['outlined' => ['label' => 'app.outlined', 'class' => 'app-heading--outlined']]]);
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 0, ['heading' => Heading::class]);
    }

    /**
     * @test
     */
    public function a_tagged_class_must_carry_the_attribute(): void
    {
        $this->setDefinition('app.not_a_block_type', (new Definition(NotABlockType::class))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no #[BlockType]');

        $this->compile();
    }

    /**
     * @test
     */
    public function a_tagged_service_must_have_an_existing_class(): void
    {
        $this->setDefinition('app.missing', (new Definition('App\Missing'))->addTag('setono_sylius_cms.block_type'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"app.missing" must have an existing class');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(BlockTypeRegistry::class);
        $this->setDefinition('app.heading', (new Definition(Heading::class))->addTag('setono_sylius_cms.block_type'));

        $this->compile();

        $this->assertContainerBuilderHasService('app.heading');
    }
}
