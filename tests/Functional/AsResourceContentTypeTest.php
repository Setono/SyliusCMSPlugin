<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AsResource\Recipe;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AsResourceContentTypeKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A content type registered with #[AsResource] is found among the Sylius resources like one registered under
 * sylius_resource.resources: the registry has it, its code is its discriminator, and its columns are prefixed with it.
 * See "Content types" in docs/spec/extending.md
 */
final class AsResourceContentTypeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AsResourceContentTypeKernel::class;
    }

    /**
     * @test
     */
    public function it_registers_a_content_type_registered_with_as_resource(): void
    {
        /** @var ContentTypeRegistryInterface $contentTypes */
        $contentTypes = self::getContainer()->get(ContentTypeRegistryInterface::class);

        $recipe = $contentTypes->get('recipe');
        self::assertSame(Recipe::class, $recipe->modelClass);
        self::assertSame('app.recipe', $recipe->resourceAlias);
        self::assertSame($recipe, $contentTypes->getByEntry(new Recipe()));
    }

    /**
     * @test
     */
    public function it_maps_a_content_type_registered_with_as_resource_under_its_code_with_its_columns_prefixed(): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');

        self::assertSame(Recipe::class, $manager->getClassMetadata(Entry::class)->discriminatorMap['recipe'] ?? null);
        self::assertSame('recipe', $manager->getClassMetadata(Recipe::class)->discriminatorValue);
        self::assertSame('recipe_servings', $manager->getClassMetadata(Recipe::class)->getColumnName('servings'));
    }
}
