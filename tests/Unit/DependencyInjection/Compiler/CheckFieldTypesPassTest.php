<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\CheckFieldTypesPass;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistry;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\ConditionKindWithTextAgain;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\EntryWithTextAgain;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\SourceWithTextAgain;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithFieldWithoutControl;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithFieldWithoutFieldType;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithInvalidControl;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithInvalidFieldTypeCode;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithStars;
use Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithTextAgain;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Field types are declared on their field attribute classes and found where fields use them: on block types, layouts,
 * content types, and the parameters of condition kinds and sources. Their codes are unique, like every other kind's,
 * and a project's own field type brings a field control. See "Adding a field type" in docs/spec/extending.md
 */
final class CheckFieldTypesPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // As the Register*Pass passes leave them, with what's registered
        $this->setDefinition(BlockTypeRegistry::class, new Definition(BlockTypeRegistry::class, [['heading' => Heading::class]]));
        $this->setDefinition(LayoutRegistry::class, new Definition(LayoutRegistry::class, [[]]));
        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[]]));
        $this->setDefinition(ConditionKindRegistry::class, new Definition(ConditionKindRegistry::class, [[]]));
        $this->setDefinition(SourceRegistry::class, new Definition(SourceRegistry::class, [[]]));
        $this->setDefinition(BindablePropertyRegistry::class, new Definition(BindablePropertyRegistry::class, [[]]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CheckFieldTypesPass());
    }

    /**
     * @test
     */
    public function it_accepts_a_projects_own_field_types_with_codes_of_their_own_and_field_controls(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['heading' => Heading::class, 'stars' => WithStars::class]);

        $this->compile();

        $this->assertContainerBuilderHasService(BlockTypeRegistry::class);
    }

    /**
     * @test
     *
     * @dataProvider provideWhereFieldsAre
     *
     * @param array<string, mixed> $classes what the registry was handed, by code
     */
    public function it_refuses_a_field_type_with_the_code_of_another(string $registry, array $classes): void
    {
        $this->container->getDefinition($registry)->replaceArgument(0, $classes);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field type code "text" is used by both Setono\SyliusCMSPlugin\Field\Text and Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\TextAgain');

        $this->compile();
    }

    /**
     * @return iterable<string, array{class-string, array<string, mixed>}>
     */
    public static function provideWhereFieldsAre(): iterable
    {
        yield 'a block type' => [BlockTypeRegistry::class, ['heading' => Heading::class, 'text_again' => WithTextAgain::class]];
        yield 'a layout' => [LayoutRegistry::class, ['page' => ['text_again' => WithTextAgain::class]]];
        yield 'a content type' => [ContentTypeRegistry::class, ['post' => ['model' => EntryWithTextAgain::class, 'translation' => null, 'resource' => 'app.post']]];
        yield 'a content type\'s translation' => [ContentTypeRegistry::class, ['post' => ['model' => Heading::class, 'translation' => EntryWithTextAgain::class, 'resource' => 'app.post']]];
        yield 'a condition kind\'s parameters' => [ConditionKindRegistry::class, ['text_again' => ConditionKindWithTextAgain::class]];
        yield 'a source\'s parameters' => [SourceRegistry::class, ['text_again' => SourceWithTextAgain::class]];
        yield 'a bindable property\'s parameters' => [BindablePropertyRegistry::class, ['product.text_again' => ['context_key' => 'product', 'code' => 'text_again', 'label' => 'app.text', 'type' => 'text', 'params' => WithTextAgain::class]]];
    }

    /**
     * A field type the plugin ships has its code even where no field uses it
     *
     * @test
     */
    public function it_refuses_the_code_of_a_shipped_field_type_that_no_field_uses(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['text_again' => WithTextAgain::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field type code "text" is used by both');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_field_type_code_that_is_not_snake_case(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['stars' => WithInvalidFieldTypeCode::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field type code "App-Stars" of Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\InvalidFieldTypeCode must match');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_project_field_type_without_a_field_control(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['hearts' => WithFieldWithoutControl::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field type Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithoutControl needs a field control');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_field_control_that_is_not_a_custom_element_name(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['hearts' => WithInvalidControl::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field control "Hearts" of the field type Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\InvalidControl must be a custom element name');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_field_attribute_without_a_field_type(): void
    {
        $this->container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, ['hearts' => WithFieldWithoutFieldType::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field attribute Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture\WithoutFieldType must carry #[FieldType]');

        $this->compile();
    }
}
