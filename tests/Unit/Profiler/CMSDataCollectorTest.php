<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Profiler\CMSDataCollector;
use Setono\SyliusCMSPlugin\Profiler\RecordedFailure;
use Setono\SyliusCMSPlugin\Profiler\RenderedSlots;
use Setono\SyliusCMSPlugin\Query\NotVisibleReason;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CMSDataCollectorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<VisibleEntriesQueryInterface> */
    private ObjectProphecy $visibleEntries;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    private RenderedSlots $slots;

    protected function setUp(): void
    {
        $this->visibleEntries = $this->prophesize(VisibleEntriesQueryInterface::class);
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->entries->findByUuids(Argument::any())->willReturn([]);
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        $this->placements->findOutsideChannel(Argument::cetera())->willReturn([]);
        $channel = new Channel();
        $channel->setCode('WEB');
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($channel);
        $this->slots = new RenderedSlots();
    }

    /**
     * The reason for each candidate: its outcome, and for a snippet that isn't visible, the first rule of "When an
     * entry shows" it fails
     *
     * @test
     */
    public function it_lists_every_candidate_placement_of_a_slot_in_editor_order_with_whether_it_showed_and_if_not_why(): void
    {
        $wins = self::snippet('Free shipping');
        $context = self::snippet('Size guide');
        $capped = self::snippet('Winter sale');
        $outOfScope = self::snippet('Shoes only');
        $draft = self::snippet('Draft');
        $wholesale = self::snippet('Wholesale prices');
        $renderContext = Rendering::context();
        $slot = $this->slots->start('shop.top');
        $slot->context = [];
        $slot->renderContext = $renderContext;
        $slot->candidates = [
            new CandidatePlacement(self::placement('01', $outOfScope), PlacementOutcome::ScopeDoesNotMatch),
            new CandidatePlacement(self::placement('02', $draft), PlacementOutcome::SnippetNotVisible),
            new CandidatePlacement(self::placement('03', $context), PlacementOutcome::ContextNotProvided, $context),
            new CandidatePlacement(self::placement('04', $wins), PlacementOutcome::Wins, $wins),
            new CandidatePlacement(self::placement('05', $wholesale), PlacementOutcome::SnippetNotVisible),
            new CandidatePlacement(self::placement('06', $capped), PlacementOutcome::CapReached, $capped),
        ];
        $this->slots->end($slot, 1.5);
        $this->visibleEntries->whyNotVisible('snippet', [$draft->getUuid(), $wholesale->getUuid()], $renderContext)->willReturn([
            $wholesale->getUuid() => NotVisibleReason::OutsideAudience,
            $draft->getUuid() => NotVisibleReason::NotPublished,
        ]);
        $this->entries->findByUuids([$outOfScope->getUuid(), $draft->getUuid(), $wholesale->getUuid()])->willReturn([$draft, $outOfScope, $wholesale]);

        $candidates = $this->collected()->getSlots()[0]['candidates'];

        self::assertSame([
            ['placement' => self::uuid('01'), 'snippet' => $outOfScope->getUuid(), 'title' => 'Shoes only', 'outcome' => 'scope_does_not_match', 'reason' => null],
            ['placement' => self::uuid('02'), 'snippet' => $draft->getUuid(), 'title' => 'Draft', 'outcome' => 'snippet_not_visible', 'reason' => 'not_published'],
            ['placement' => self::uuid('03'), 'snippet' => $context->getUuid(), 'title' => 'Size guide', 'outcome' => 'context_not_provided', 'reason' => null],
            ['placement' => self::uuid('04'), 'snippet' => $wins->getUuid(), 'title' => 'Free shipping', 'outcome' => 'wins', 'reason' => null],
            ['placement' => self::uuid('05'), 'snippet' => $wholesale->getUuid(), 'title' => 'Wholesale prices', 'outcome' => 'snippet_not_visible', 'reason' => 'outside_audience'],
            ['placement' => self::uuid('06'), 'snippet' => $capped->getUuid(), 'title' => 'Winter sale', 'outcome' => 'cap_reached', 'reason' => null],
        ], $candidates);
    }

    /**
     * Such as a deleted one, which the visible query doesn't find
     *
     * @test
     */
    public function it_names_a_candidate_whose_snippet_it_can_not_find_by_its_uuid_and_gives_no_finer_reason(): void
    {
        $gone = new Snippet();
        $slot = $this->slots->start('shop.top');
        $slot->renderContext = Rendering::context();
        $slot->candidates = [new CandidatePlacement(self::placement('01', $gone), PlacementOutcome::SnippetNotVisible)];
        $this->visibleEntries->whyNotVisible(Argument::cetera())->willReturn([]);

        $candidate = $this->collected()->getSlots()[0]['candidates'][0] ?? null;

        self::assertIsArray($candidate);
        self::assertSame($gone->getUuid(), $candidate['title']);
        self::assertNull($candidate['reason']);
    }

    /**
     * @test
     */
    public function it_lists_each_slot_rendered_with_its_label_where_it_rendered_the_context_it_resolved_its_render_time_and_what_threw(): void
    {
        $product = new Product();
        $product->setCode('T_SHIRT');
        $slot = $this->slots->start('product.after_price');
        $slot->context = ['product' => ['object' => $product, 'passed' => true]];
        $slot->candidates = [];
        $slot->failures[] = new RecordedFailure('0193a5be-0000-7000-8000-0000000000aa', '0193a5be-0000-7000-8000-0000000000bb', 'broken', null, 'Boom');
        $this->slots->end($slot, 2.5);
        $header = $this->slots->start('taxon.header', 'sylius_shop_partial_taxon_show_by_slug');
        $header->unresolvedKey = 'taxon';
        $this->slots->end($header, 0.5);
        $this->slots->end($this->slots->start('shop.footer'), 0.25);
        $this->slots->failed(new RecordedFailure('0193a5be-0000-7000-8000-0000000000cc', '0193a5be-0000-7000-8000-0000000000dd', 'broken', null, 'Boom in the page'));

        $collector = $this->collected();

        self::assertSame([
            [
                'identifier' => 'product.after_price',
                'label' => 'setono_sylius_cms.slot.product.after_price',
                'templateEvent' => 'sylius.shop.product.show.right_sidebar',
                'subRequest' => null,
                'context' => [['key' => 'product', 'class' => Product::class, 'id' => 'T_SHIRT', 'passed' => true]],
                'unresolvedKey' => null,
                'candidates' => [],
                'shown' => 0,
                'duration' => 2.5,
                'failures' => [['entry' => '0193a5be-0000-7000-8000-0000000000aa', 'block' => '0193a5be-0000-7000-8000-0000000000bb', 'blockType' => 'broken', 'placement' => null, 'message' => 'Boom']],
            ],
            [
                'identifier' => 'taxon.header',
                'label' => 'setono_sylius_cms.slot.taxon.header',
                'templateEvent' => 'sylius.shop.taxon.header',
                'subRequest' => 'sylius_shop_partial_taxon_show_by_slug',
                'context' => null,
                'unresolvedKey' => 'taxon',
                'candidates' => null,
                'shown' => 0,
                'duration' => 0.5,
                'failures' => [],
            ],
            [
                'identifier' => 'shop.footer',
                'label' => 'setono_sylius_cms.slot.shop.footer',
                'templateEvent' => null,
                'subRequest' => null,
                'context' => null,
                'unresolvedKey' => null,
                'candidates' => null,
                'shown' => 0,
                'duration' => 0.25,
                'failures' => [],
            ],
        ], $collector->getSlots());
        self::assertSame([['entry' => '0193a5be-0000-7000-8000-0000000000cc', 'block' => '0193a5be-0000-7000-8000-0000000000dd', 'blockType' => 'broken', 'placement' => null, 'message' => 'Boom in the page']], $collector->getFailuresOutsideSlots());
        self::assertSame(2, $collector->getFailureCount());
    }

    /**
     * Symfony collects for each sub-request, then for the main request, which lists them all. The reasons are read once
     *
     * @test
     */
    public function it_reads_why_snippets_are_not_visible_once_however_often_it_collects(): void
    {
        $draft = self::snippet('Draft');
        $slot = $this->slots->start('shop.top');
        $slot->renderContext = Rendering::context();
        $slot->candidates = [new CandidatePlacement(self::placement('01', $draft), PlacementOutcome::SnippetNotVisible)];
        $this->visibleEntries->whyNotVisible(Argument::cetera())->willReturn([$draft->getUuid() => NotVisibleReason::Disabled])->shouldBeCalledOnce();
        $this->entries->findByUuids(Argument::any())->willReturn([$draft])->shouldBeCalledOnce();
        $this->placements->findOutsideChannel(Argument::cetera())->willReturn([])->shouldBeCalledOnce();

        $collector = $this->collector();
        $collector->collect(new Request(), new Response());
        $collector->collect(new Request(), new Response());

        self::assertSame('disabled', $collector->getSlots()[0]['candidates'][0]['reason'] ?? null);
    }

    /**
     * The shop never reads them, so the profiler reads them when it collects, with one query for every slot
     *
     * @test
     */
    public function it_lists_the_placements_whose_snippet_is_not_in_the_channel_among_the_candidates_in_editor_order(): void
    {
        $wins = self::snippet('Free shipping');
        $capped = self::snippet('Winter sale');
        $elsewhere = self::snippet('Danish shipping');
        $slot = $this->slots->start('shop.top');
        $slot->renderContext = Rendering::context();
        $slot->candidates = [
            new CandidatePlacement(self::placement('01', $wins), PlacementOutcome::Wins, $wins),
            new CandidatePlacement(self::placement('03', $capped), PlacementOutcome::CapReached, $capped),
        ];
        $this->slots->start('shop.footer');
        $elsewhereInTop = new Placement($elsewhere, 'shop.top', 2);
        $elsewhereInFooter = new Placement($elsewhere, 'shop.footer', 0);
        $this->placements->findOutsideChannel(['shop.top', 'shop.footer'], 'WEB')->willReturn([$elsewhereInTop, $elsewhereInFooter])->shouldBeCalledOnce();

        $slots = $this->collected()->getSlots();

        self::assertSame([
            [self::uuid('01'), 'Free shipping', 'wins', null],
            [$elsewhereInTop->getUuid(), 'Danish shipping', 'snippet_not_visible', 'not_in_channel'],
            [self::uuid('03'), 'Winter sale', 'cap_reached', null],
        ], array_map(static fn (array $candidate): array => [$candidate['placement'], $candidate['title'], $candidate['outcome'], $candidate['reason']], $slots[0]['candidates'] ?? []));
        self::assertSame(
            [['placement' => $elsewhereInFooter->getUuid(), 'snippet' => $elsewhere->getUuid(), 'title' => 'Danish shipping', 'outcome' => 'snippet_not_visible', 'reason' => 'not_in_channel']],
            $slots[1]['candidates'],
            'A slot whose only placements are other channels\' lists them',
        );
        self::assertSame(0, $slots[1]['shown']);
    }

    /**
     * Its candidates in the channel are unknown, as it stopped before it picked
     *
     * @test
     */
    public function it_lists_no_candidates_of_a_slot_whose_context_it_could_not_resolve(): void
    {
        $slot = $this->slots->start('product.after_price');
        $slot->unresolvedKey = 'product';
        $this->placements->findOutsideChannel(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->collected()->getSlots()[0]['candidates']);
    }

    /**
     * @test
     */
    public function it_counts_the_slots_that_show_something(): void
    {
        $shows = self::snippet('Free shipping');
        $slot = $this->slots->start('shop.top');
        $slot->renderContext = Rendering::context();
        $slot->candidates = [new CandidatePlacement(self::placement('01', $shows), PlacementOutcome::Wins, $shows)];
        $this->slots->start('shop.footer');

        $collector = $this->collected();

        self::assertSame(2, $collector->getSlotCount());
        self::assertSame(1, $collector->getShowingSlotCount());
    }

    /**
     * @test
     */
    public function it_forgets_what_it_collected_when_it_resets(): void
    {
        $this->slots->start('shop.top');
        $collector = $this->collected();

        $collector->reset();

        self::assertSame([], $collector->getSlots());
        self::assertSame(0, $collector->getFailureCount());
    }

    private function collected(): CMSDataCollector
    {
        $collector = $this->collector();
        $collector->collect(new Request(), new Response());

        return $collector;
    }

    private function collector(): CMSDataCollector
    {
        return new CMSDataCollector($this->slots, new SlotRegistry([
            'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => 1, 'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 30]],
            'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
            'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.product.show.right_sidebar', 'priority' => 28]],
            'taxon.header' => ['label' => 'setono_sylius_cms.slot.taxon.header', 'provides' => ['taxon'], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.taxon.header', 'priority' => 1]],
        ]), $this->visibleEntries->reveal(), $this->entries->reveal(), $this->placements->reveal(), $this->channelContext->reveal());
    }

    private static function snippet(string $title): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setFallbackLocale('en_US');
        $snippet->setTitle($title);

        return $snippet;
    }

    private static function placement(string $number, SnippetInterface $snippet): PlacementData
    {
        return new PlacementData(self::uuid($number), $snippet->getUuid(), 'shop.top', [], (int) $number);
    }

    private static function uuid(string $number): string
    {
        return '0193a5be-0000-7000-8000-0000000000' . $number;
    }
}
