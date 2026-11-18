<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Doctrine\RootAssociationsListener;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class RootAssociationsListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Sylius's ORMMappedSuperClassSubscriber takes the to-many associations off a resource's mapped superclass, such as
     * Page when a project's subclass is the page model, those Page inherits from Entry included, so the subclass would
     * have no channels
     *
     * @test
     */
    public function it_puts_the_roots_associations_a_content_types_model_lost_back_as_inherited_from_the_root(): void
    {
        $root = new ClassMetadata(Entry::class);
        $root->mapOneToMany(['fieldName' => 'channels', 'targetEntity' => EntryChannel::class, 'mappedBy' => 'entry']);

        $subclass = new ClassMetadata(Recipe::class);
        $subclass->mapOneToMany(['fieldName' => 'homepages', 'targetEntity' => Homepage::class, 'mappedBy' => 'page']);

        $this->load($subclass, $root);

        self::assertSame(['homepages', 'channels'], array_keys($subclass->associationMappings));
        self::assertSame(Entry::class, $subclass->getAssociationMapping('channels')['inherited'] ?? null);
        self::assertSame(Entry::class, $subclass->getAssociationMapping('channels')['declared'] ?? null);
    }

    /**
     * @test
     */
    public function it_leaves_the_associations_a_content_types_model_has_and_other_classes_alone(): void
    {
        $root = new ClassMetadata(Entry::class);
        $root->mapOneToMany(['fieldName' => 'channels', 'targetEntity' => EntryChannel::class, 'mappedBy' => 'entry']);

        $page = new ClassMetadata(Page::class);
        $page->addInheritedAssociationMapping(['fieldName' => 'channels', 'targetEntity' => EntryChannel::class, 'mappedBy' => 'entry', 'type' => ClassMetadata::ONE_TO_MANY, 'inherited' => Entry::class, 'sourceEntity' => Entry::class, 'isOwningSide' => false]);
        $this->load($page, $root);
        self::assertSame(['channels'], array_keys($page->associationMappings));

        $other = new ClassMetadata(EntryTranslation::class);
        $this->load($other, $root);
        self::assertSame([], $other->associationMappings);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param ClassMetadata<object> $root
     */
    private function load(ClassMetadata $metadata, ClassMetadata $root): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->getClassMetadata(Entry::class)->willReturn($root);

        (new RootAssociationsListener([
            'page' => ['model' => Page::class, 'translation' => null, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]))->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $manager->reveal()));
    }
}
