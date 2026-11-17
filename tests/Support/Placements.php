<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Factory\PlacementFactoryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Slot\PlacementEditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests of slots: snippets placed in them, as the overview page places them. Create the
 * snippets with Entries::createSnippet()
 */
trait Placements
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Places the snippet in the slot, after the slot's placements, as the overview page's Add does
     */
    protected static function place(SnippetInterface $snippet, string $slot): PlacementInterface
    {
        return self::placementEditor()->add($snippet, $slot);
    }

    /**
     * Places the snippet in a slot the code doesn't declare, such as one a deploy removed, which the overview page
     * never offers. The placement goes after the slot's placements
     */
    protected static function placeInUndeclaredSlot(SnippetInterface $snippet, string $slot): PlacementInterface
    {
        return self::placeUnchecked($snippet, $slot);
    }

    /**
     * Places the snippet in the slot without asking whether it may go there, as a placement made before a deploy changed
     * the slots or the context keys is. The placement goes after the slot's placements
     */
    protected static function placeUnchecked(SnippetInterface $snippet, string $slot): PlacementInterface
    {
        /** @var PlacementFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.placement');
        $placement = $factory->createFor($snippet, $slot, self::placements()->nextPosition($slot));

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.placement');
        $manager->persist($placement);
        $manager->flush();

        return $placement;
    }

    /**
     * The snippet's placements as the database holds them, as [slot, position] by slot and then in each slot's order
     *
     * @return list<array{string, int}>
     */
    protected static function placementsOf(SnippetInterface $snippet): array
    {
        return array_map(
            static fn (PlacementInterface $placement): array => [$placement->getSlot(), $placement->getPosition()],
            self::placements()->findBySnippet($snippet),
        );
    }

    protected static function placementEditor(): PlacementEditorInterface
    {
        $editor = self::getContainer()->get(PlacementEditorInterface::class);
        self::assertInstanceOf(PlacementEditorInterface::class, $editor);

        return $editor;
    }

    protected static function placements(): PlacementRepositoryInterface
    {
        $placements = self::getContainer()->get(PlacementRepositoryInterface::class);
        self::assertInstanceOf(PlacementRepositoryInterface::class, $placements);

        return $placements;
    }
}
