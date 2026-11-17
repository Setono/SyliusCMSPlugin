<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedSlot;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;

/**
 * The slot a snippet's preview shows it in: the previewed placement wins there with the snippet's draft, as if it won
 * for shoppers, and the top bar says which snippets those shoppers see instead when it loses its capped slot. See
 * "Snippet preview" in docs/spec/builder.md and "Preview mode and share links" in docs/spec/rendering.md
 */
final class PreviewedSlotTest extends TestCase
{
    private const PREVIEWED = '0193a5be-0000-7000-8000-0000000000c2';

    /**
     * @test
     */
    public function it_lets_the_previewed_placement_win_with_the_draft_and_takes_a_seat_of_the_cap_from_those_above_it(): void
    {
        $draft = self::snippet('Summer sale (draft)');
        $winter = self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, self::snippet('Winter sale'));
        $previewed = self::candidate(self::PREVIEWED, PlacementOutcome::CapReached, self::snippet('Summer sale'));
        $hidden = self::candidate('0193a5be-0000-7000-8000-0000000000c3', PlacementOutcome::SnippetNotVisible);

        $candidates = PreviewedSlot::asIfItWon(self::slot(cap: 1), [$winter, $previewed, $hidden], self::PREVIEWED, $draft);

        self::assertEquals([
            new CandidatePlacement($winter->placement, PlacementOutcome::CapReached, $winter->snippet),
            new CandidatePlacement($previewed->placement, PlacementOutcome::Wins, $draft, previewed: true),
            $hidden,
        ], $candidates);
    }

    /**
     * @test
     */
    public function it_keeps_the_other_winners_while_the_cap_has_seats(): void
    {
        $draft = self::snippet('Summer sale (draft)');
        $winter = self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, self::snippet('Winter sale'));
        $previewed = self::candidate(self::PREVIEWED, PlacementOutcome::SnippetNotVisible);
        $spring = self::candidate('0193a5be-0000-7000-8000-0000000000c3', PlacementOutcome::Wins, self::snippet('Spring sale'));
        $autumn = self::candidate('0193a5be-0000-7000-8000-0000000000c4', PlacementOutcome::CapReached, self::snippet('Autumn sale'));

        $candidates = PreviewedSlot::asIfItWon(self::slot(cap: 2), [$winter, $previewed, $spring, $autumn], self::PREVIEWED, $draft);

        self::assertSame(
            [PlacementOutcome::Wins, PlacementOutcome::Wins, PlacementOutcome::CapReached, PlacementOutcome::CapReached],
            array_map(static fn (CandidatePlacement $candidate): PlacementOutcome => $candidate->outcome, $candidates),
        );
        self::assertTrue($candidates[1]->previewed);
        self::assertSame($draft, $candidates[1]->snippet, 'The draft shows, though the snippet was never published');
    }

    /**
     * @test
     */
    public function it_shows_the_previewed_placement_whose_scope_doesn_t_match_the_sample_object(): void
    {
        $draft = self::snippet('Shoe care');
        $previewed = self::candidate(self::PREVIEWED, PlacementOutcome::ScopeDoesNotMatch);
        $other = self::candidate('0193a5be-0000-7000-8000-0000000000c3', PlacementOutcome::Wins, self::snippet('Free returns'));

        self::assertEquals([
            new CandidatePlacement($previewed->placement, PlacementOutcome::Wins, $draft, previewed: true),
            $other,
        ], PreviewedSlot::asIfItWon(self::slot(), [$previewed, $other], self::PREVIEWED, $draft));
    }

    /**
     * @test
     */
    public function it_leaves_a_slot_without_the_previewed_placement_as_it_is(): void
    {
        $candidates = [self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, self::snippet('Winter sale'))];

        self::assertSame($candidates, PreviewedSlot::asIfItWon(self::slot(cap: 1), $candidates, self::PREVIEWED, self::snippet('Draft')));
    }

    /**
     * @test
     */
    public function it_names_the_snippets_shoppers_see_where_the_previewed_placement_loses_its_capped_slot(): void
    {
        $winter = self::snippet('Winter sale');
        $candidates = [
            self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, $winter),
            self::candidate(self::PREVIEWED, PlacementOutcome::CapReached, self::snippet('Summer sale')),
        ];

        self::assertSame([$winter], PreviewedSlot::shownInstead(self::slot(cap: 1), $candidates, self::PREVIEWED));
    }

    /**
     * The preview ignores the snippet's own visibility, so a draft that was never published still loses where the slot
     * is full above it
     *
     * @test
     */
    public function it_names_them_for_a_snippet_shoppers_can_t_see_yet_where_the_cap_is_full_above_it(): void
    {
        $winter = self::snippet('Winter sale');
        $candidates = [
            self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, $winter),
            self::candidate(self::PREVIEWED, PlacementOutcome::SnippetNotVisible),
            self::candidate('0193a5be-0000-7000-8000-0000000000c3', PlacementOutcome::CapReached, self::snippet('Spring sale')),
        ];

        self::assertSame([$winter], PreviewedSlot::shownInstead(self::slot(cap: 1), $candidates, self::PREVIEWED));
        self::assertNull(PreviewedSlot::shownInstead(self::slot(cap: 2), $candidates, self::PREVIEWED), 'A seat is left for it');
    }

    /**
     * @test
     */
    public function it_names_none_where_the_previewed_placement_wins_or_its_scope_doesn_t_match(): void
    {
        $winter = self::candidate('0193a5be-0000-7000-8000-0000000000c1', PlacementOutcome::Wins, self::snippet('Winter sale'));

        self::assertNull(PreviewedSlot::shownInstead(self::slot(cap: 2), [$winter, self::candidate(self::PREVIEWED, PlacementOutcome::Wins, self::snippet('Summer sale'))], self::PREVIEWED));
        self::assertNull(PreviewedSlot::shownInstead(self::slot(cap: 1), [$winter, self::candidate(self::PREVIEWED, PlacementOutcome::ScopeDoesNotMatch)], self::PREVIEWED));
        self::assertNull(PreviewedSlot::shownInstead(self::slot(), [$winter, self::candidate(self::PREVIEWED, PlacementOutcome::SnippetNotVisible)], self::PREVIEWED), 'An uncapped slot shows it');
        self::assertNull(PreviewedSlot::shownInstead(self::slot(cap: 1), [$winter], self::PREVIEWED), 'Not in the slot');
    }

    private static function slot(?int $cap = null): SlotMetadata
    {
        return new SlotMetadata('shop.top', 'setono_sylius_cms.slot.shop.top', [], $cap);
    }

    private static function candidate(string $uuid, PlacementOutcome $outcome, ?SnippetInterface $snippet = null): CandidatePlacement
    {
        return new CandidatePlacement(new PlacementData($uuid, $snippet?->getUuid() ?? 'snippet', 'shop.top', [], 0), $outcome, $snippet);
    }

    private static function snippet(string $title): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setTitle($title);

        return $snippet;
    }
}
