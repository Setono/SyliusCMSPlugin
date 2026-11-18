<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\CappedSlotWithoutCatchAll;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A capped slot without a catch-all placement, one with an empty page scope whose snippet is published, enabled and for
 * everyone, so some pages or shoppers may get nothing there. See "Capped slots" in docs/spec/targeting.md and "Problems" in
 * docs/spec/admin.md
 */
final class CappedSlotWithoutCatchAllTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    protected function setUp(): void
    {
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_warns_once_for_each_capped_slot_a_snippet_is_placed_in_that_has_no_catch_all_placement_of_any_snippet(): void
    {
        $snippet = new Snippet();
        $forWholesale = self::snippetFor(Audience::only('wholesale'));
        $disabled = self::published(new Snippet());
        $disabled->setEnabled(false);
        $inTop = self::scoped(new Placement($snippet, 'shop.top', 0));
        $againInTop = self::scoped(new Placement($snippet, 'shop.top', 2));
        $inHero = self::scoped(new Placement($snippet, 'app.hero', 0));
        $this->placements->findBySnippet($snippet)->willReturn([$inHero, $inTop, $againInTop, new Placement($snippet, 'shop.footer', 0), new Placement($snippet, 'app.gone', 0)]);
        $this->placements->findBySlots(['app.hero', 'shop.top'])->willReturn([
            $inHero,
            // A catch-all of another snippet, anywhere in the slot
            new Placement(self::published(new Snippet()), 'app.hero', 1),
            new Placement($forWholesale, 'shop.top', 1),
            // Nor is a snippet for everyone a catch-all while it's unpublished or disabled
            new Placement(new Snippet(), 'shop.top', 3),
            new Placement($disabled, 'shop.top', 4),
            $inTop,
            $againInTop,
        ]);

        self::assertEquals([
            new Problem(
                'capped_slot_without_catch_all',
                Severity::Warning,
                $snippet,
                new TranslatableMessage('setono_sylius_cms.problem.capped_slot_without_catch_all', ['%slot%' => new TranslatableMessage('setono_sylius_cms.slot.shop.top'), '%cap%' => 1]),
                Fix::inPlacements($snippet),
            ),
        ], [...$this->kind()->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_snippet_only_in_uncapped_slots_or_an_entry_that_is_not_a_snippet(): void
    {
        $snippet = new Snippet();
        $this->placements->findBySnippet($snippet)->willReturn([self::scoped(new Placement($snippet, 'shop.footer', 0))]);
        $this->placements->findBySlots(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind()->findIn($snippet)]);
        self::assertSame([], [...$this->kind()->findIn(new Page())]);
    }

    /**
     * A placement written, or a snippet's audience changed, its publishing, enabling or disabling, or its delete, may give
     * or take the catch-all of a capped slot other snippets are placed in too
     *
     * @test
     */
    public function it_has_every_snippet_placed_in_a_capped_slot_counted_again_when_a_placement_or_what_makes_a_snippet_a_catch_all_is_written(): void
    {
        $first = new Snippet();
        $second = new Snippet();
        $this->placements->findBySlots(['app.hero', 'shop.top'])->willReturn([
            new Placement($first, 'app.hero', 0),
            new Placement($second, 'shop.top', 0),
            new Placement($first, 'shop.top', 1),
        ]);

        self::assertSame([PlacementInterface::class, EntryInterface::class], CappedSlotWithoutCatchAll::getReferencedClasses());
        self::assertSame([EntryInterface::class => ['audienceMode', 'enabled', 'lastPublishedAt']], CappedSlotWithoutCatchAll::getReferenceFields());
        self::assertSame([$first, $second], [...$this->kind()->findEntriesReferencing([new Placement(new Snippet(), 'shop.footer', 0)])]);
    }

    private function kind(): CappedSlotWithoutCatchAll
    {
        return new CappedSlotWithoutCatchAll($this->placements->reveal(), new SlotRegistry([
            'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => 1],
            'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
            'app.hero' => ['label' => 'app.cms.slot.hero', 'provides' => [], 'cap' => 2],
        ]));
    }

    private static function snippetFor(Audience $audience): Snippet
    {
        $snippet = self::published(new Snippet());
        ChannelsAndAudience::modifier()->setAudience($snippet, $audience);

        return $snippet;
    }

    private static function published(Snippet $snippet): Snippet
    {
        $snippet->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));

        return $snippet;
    }

    private static function scoped(Placement $placement): Placement
    {
        $placement->setPageScope([['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);

        return $placement;
    }
}
