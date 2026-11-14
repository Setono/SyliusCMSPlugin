<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Doctrine\DiscriminatorMapListener;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class DiscriminatorMapListenerTest extends TestCase
{
    use ProphecyTrait;

    private const CONTENT_TYPES = [
        'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
        'note' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.note'],
    ];

    /**
     * @test
     */
    public function it_maps_each_content_type_code_to_its_model(): void
    {
        $metadata = $this->load(Entry::class);

        self::assertSame(['page' => Page::class, 'recipe' => Recipe::class, 'note' => Recipe::class], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_maps_the_content_types_with_a_translation_class_of_their_own_to_it(): void
    {
        $metadata = $this->load(EntryTranslation::class);

        self::assertSame(['page' => PageTranslation::class], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_leaves_other_classes_alone(): void
    {
        self::assertSame([], $this->load(Page::class)->discriminatorMap);
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function load(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);

        (new DiscriminatorMapListener(self::CONTENT_TYPES))->loadClassMetadata(
            new LoadClassMetadataEventArgs($metadata, $this->prophesize(EntityManagerInterface::class)->reveal()),
        );

        return $metadata;
    }
}
