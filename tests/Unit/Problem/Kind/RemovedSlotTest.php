<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\RemovedSlot;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

final class RemovedSlotTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_each_placement_in_a_slot_the_code_no_longer_declares_as_a_warning_fixed_in_the_placements_section(): void
    {
        $snippet = new Snippet();
        $placements = $this->prophesize(PlacementRepositoryInterface::class);
        $placements->findBySnippet($snippet)->willReturn([
            new Placement($snippet, 'shop.footer', 0),
            new Placement($snippet, 'shop.removed', 0),
            new Placement($snippet, 'app.gone', 3),
        ]);

        self::assertEquals([
            new Problem('removed_slot', Severity::Warning, $snippet, new TranslatableMessage('setono_sylius_cms.problem.removed_slot', ['%slot%' => 'shop.removed']), Fix::inPlacements($snippet)),
            new Problem('removed_slot', Severity::Warning, $snippet, new TranslatableMessage('setono_sylius_cms.problem.removed_slot', ['%slot%' => 'app.gone']), Fix::inPlacements($snippet)),
        ], [...self::kind($placements->reveal())->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_snippet_whose_placements_are_all_in_declared_slots_or_an_entry_that_is_not_a_snippet(): void
    {
        $snippet = new Snippet();
        $placements = $this->prophesize(PlacementRepositoryInterface::class);
        $placements->findBySnippet($snippet)->willReturn([new Placement($snippet, 'shop.footer', 0)]);

        self::assertSame([], [...self::kind($placements->reveal())->findIn($snippet)]);
        self::assertSame([], [...self::kind($placements->reveal())->findIn(new Page())]);
    }

    private static function kind(PlacementRepositoryInterface $placements): RemovedSlot
    {
        return new RemovedSlot($placements, new SlotRegistry(['shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null]]));
    }
}
