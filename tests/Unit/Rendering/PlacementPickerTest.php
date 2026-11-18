<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\FailureHandlerInterface;
use Setono\SyliusCMSPlugin\Rendering\PageScopeMatcher;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Rendering\PlacementPicker;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\VisibleSnippetsInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\OnProductPage;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Which of a slot's placements show, decided without rendering: those whose page scope matches the page and whose
 * snippet is visible to the shopper, in editor order, and in a capped slot only the first `cap` of them. See "Capped
 * slots" in docs/spec/targeting.md and "Rendering a slot" in docs/spec/rendering.md
 */
final class PlacementPickerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<VisibleSnippetsInterface> */
    private ObjectProphecy $visibleSnippets;

    protected function setUp(): void
    {
        // Visible as the visible-entries query has it, here only by the audience: the shopper's customer group
        $this->visibleSnippets = $this->prophesize(VisibleSnippetsInterface::class);
        $this->visibleSnippets->get(Argument::cetera())->will(function (array $arguments): array {
            /** @var list<string> $uuids */
            $uuids = $arguments[0];
            /** @var RenderContext $context */
            $context = $arguments[1];

            return array_filter(
                array_intersect_key(PlacementPickerTest::$snippets, array_flip($uuids)),
                static fn (SnippetInterface $snippet): bool => $snippet->isPublished() && $snippet->getAudience()->allows($context->customerGroup),
            );
        });
        self::$snippets = [];
        self::$placements = [];
    }

    /** @var array<string, SnippetInterface> the snippets the tests make, by UUID */
    public static array $snippets = [];

    /** @var list<string> the snippet UUIDs of the placements the tests make, in the order they're made */
    private static array $placements = [];

    /**
     * @test
     */
    public function it_picks_every_placement_whose_page_scope_matches_and_whose_snippet_is_visible_in_an_uncapped_slot_in_the_order_given(): void
    {
        $sizeGuide = self::placement(self::snippet('Size guide'), [self::onProductPage()]);
        $offProductPages = self::placement(self::snippet('Off product pages'), [self::onProductPage(negate: true)]);
        $unpublished = self::placement(self::snippet('Unpublished', published: false));
        $brandStory = self::placement(self::snippet('Brand story', context: ['product', 'brand']));
        $footer = self::placement(self::snippet('Footer'));

        $candidates = $this->picker()->pick(self::slot(), [$sizeGuide, $offProductPages, $unpublished, $brandStory, $footer], self::onAProductPage());

        self::assertSame([
            [$sizeGuide, PlacementOutcome::Wins],
            [$offProductPages, PlacementOutcome::ScopeDoesNotMatch],
            [$unpublished, PlacementOutcome::SnippetNotVisible],
            [$brandStory, PlacementOutcome::ContextNotProvided],
            [$footer, PlacementOutcome::Wins],
        ], self::outcomes($candidates));
        self::assertSame([self::$snippets[$sizeGuide->snippetUuid], self::$snippets[$footer->snippetUuid]], self::winningSnippets($candidates));
    }

    /**
     * @test
     */
    public function it_picks_the_first_cap_matching_placements_of_a_capped_slot_and_the_cap_is_reached_for_the_rest(): void
    {
        $offProductPages = self::placement(self::snippet('Off product pages'), [self::onProductPage(negate: true)]);
        $hero = self::placement(self::snippet('Hero'));
        $unpublished = self::placement(self::snippet('Unpublished', published: false));
        $sale = self::placement(self::snippet('Sale'));
        $fallback = self::placement(self::snippet('Fallback'));

        $candidates = $this->picker()->pick(self::slot(cap: 2), [$offProductPages, $hero, $unpublished, $sale, $fallback], self::onAProductPage());

        self::assertSame([
            [$offProductPages, PlacementOutcome::ScopeDoesNotMatch],
            [$hero, PlacementOutcome::Wins],
            [$unpublished, PlacementOutcome::SnippetNotVisible],
            [$sale, PlacementOutcome::Wins],
            [$fallback, PlacementOutcome::CapReached],
        ], self::outcomes($candidates));
        self::assertSame(self::$snippets[$fallback->snippetUuid], $candidates[4]->snippet, 'A placement the cap leaves out still has its visible snippet');
    }

    /**
     * A placement whose snippet's audience leaves the shopper out doesn't match, so the next placement wins for them.
     * See ADR 0010
     *
     * @test
     */
    public function it_lets_the_next_placement_win_for_a_shopper_the_first_one_s_audience_leaves_out(): void
    {
        $wholesale = self::placement(self::snippet('Wholesale prices', audience: Audience::only('wholesale')));
        $retail = self::placement(self::snippet('Free shipping', audience: Audience::except('wholesale')));
        $catchAll = self::placement(self::snippet('Welcome'));
        $placements = [$wholesale, $retail, $catchAll];

        self::assertSame(['Wholesale prices'], self::winningTitles($this->picker()->pick(self::slot(cap: 1), $placements, Rendering::context(customerGroup: 'wholesale'))));
        self::assertSame(['Free shipping'], self::winningTitles($this->picker()->pick(self::slot(cap: 1), $placements, Rendering::context(customerGroup: '@guest'))));
        self::assertSame(['Free shipping', 'Welcome'], self::winningTitles($this->picker()->pick(self::slot(cap: 2), $placements, Rendering::context(customerGroup: 'retail'))));
    }

    /**
     * The winners are picked without rendering anything, so a snippet whose only block is hidden still wins, and shows
     * nothing. See "Capped slots" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_lets_a_snippet_whose_blocks_are_all_hidden_win(): void
    {
        $expired = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1, fields: ['text' => ['en_US' => 'Summer sale']]);
        $expired->conditions = [['kind' => 'date_window', 'negate' => false, 'params' => ['from' => null, 'until' => '2020-01-01T00:00:00Z']]];
        $summerSale = self::placement(self::snippet('Summer sale', blocks: [$expired]));
        $fallback = self::placement(self::snippet('Fallback'));

        self::assertSame(['Summer sale'], self::winningTitles($this->picker()->pick(self::slot(cap: 1), [$summerSale, $fallback], Rendering::context())));
    }

    /**
     * @test
     */
    public function it_counts_a_snippet_placed_twice_in_a_capped_slot_twice(): void
    {
        $snippet = self::snippet('Twice');
        $first = self::placement($snippet);
        $second = self::placement($snippet);
        $third = self::placement(self::snippet('Third'));

        self::assertSame(
            [[$first, PlacementOutcome::Wins], [$second, PlacementOutcome::Wins], [$third, PlacementOutcome::CapReached]],
            self::outcomes($this->picker()->pick(self::slot(cap: 2), [$first, $second, $third], Rendering::context())),
        );
    }

    /**
     * @test
     */
    public function it_asks_for_no_snippet_when_no_placement_s_page_scope_matches(): void
    {
        $this->visibleSnippets->get(Argument::cetera())->shouldNotBeCalled();
        $onProductPages = self::placement(self::snippet('On product pages'), [self::onProductPage()]);

        self::assertSame([[$onProductPages, PlacementOutcome::ScopeDoesNotMatch]], self::outcomes($this->picker()->pick(self::slot(), [$onProductPages], Rendering::context())));
        self::assertSame([], $this->picker()->pick(self::slot(), [], Rendering::context()));
    }

    private function picker(): PlacementPicker
    {
        return new PlacementPicker(
            new PageScopeMatcher(Conditions::matcher(['on_product_page' => new OnProductPage()]), new ReferenceLoader(new ServiceLocator([])), $this->prophesize(FailureHandlerInterface::class)->reveal()),
            $this->visibleSnippets->reveal(),
        );
    }

    private static function slot(?int $cap = null): SlotMetadata
    {
        return new SlotMetadata('product.after_price', 'setono_sylius_cms.slot.product.after_price', ['product'], $cap);
    }

    private static function onAProductPage(): RenderContext
    {
        return Rendering::context(hostPageContext: ['product' => new Product()]);
    }

    /**
     * @param list<string> $context the context keys it needs
     * @param list<StoredBlock> $blocks
     */
    private static function snippet(string $title, bool $published = true, array $context = [], ?Audience $audience = null, array $blocks = []): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setFallbackLocale('en_US');
        $snippet->setTitle($title);
        $snippet->setContext($context);
        $snippet->setBlockTree(new StoredTree(null, $blocks));
        if ($published) {
            $snippet->publish(new \DateTimeImmutable());
        }

        if (null !== $audience) {
            ChannelsAndAudience::modifier()->setAudience($snippet, $audience);
        }

        self::$snippets[$snippet->getUuid()] = $snippet;

        return $snippet;
    }

    /**
     * @param list<array<string, mixed>> $pageScope
     */
    private static function placement(SnippetInterface $snippet, array $pageScope = []): PlacementData
    {
        $position = count(self::$placements);
        self::$placements[] = $snippet->getUuid();

        return new PlacementData(sprintf('0193a5be-71b0-7a3c-9d4e-%012d', $position), $snippet->getUuid(), 'product.after_price', $pageScope, $position);
    }

    /**
     * @return array<string, mixed>
     */
    private static function onProductPage(bool $negate = false): array
    {
        return ['kind' => 'on_product_page', 'negate' => $negate, 'params' => []];
    }

    /**
     * @param list<CandidatePlacement> $candidates
     *
     * @return list<array{PlacementData, PlacementOutcome}>
     */
    private static function outcomes(array $candidates): array
    {
        return array_map(static fn (CandidatePlacement $candidate): array => [$candidate->placement, $candidate->outcome], $candidates);
    }

    /**
     * @param list<CandidatePlacement> $candidates
     *
     * @return list<SnippetInterface|null>
     */
    private static function winningSnippets(array $candidates): array
    {
        return array_values(array_map(
            static fn (CandidatePlacement $candidate): ?SnippetInterface => $candidate->snippet,
            array_filter($candidates, static fn (CandidatePlacement $candidate): bool => $candidate->wins()),
        ));
    }

    /**
     * @param list<CandidatePlacement> $candidates
     *
     * @return list<string|null>
     */
    private static function winningTitles(array $candidates): array
    {
        return array_map(static fn (?SnippetInterface $snippet): ?string => $snippet?->getTitle(), self::winningSnippets($candidates));
    }
}
