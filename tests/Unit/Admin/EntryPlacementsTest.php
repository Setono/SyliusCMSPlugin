<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryPlacement;
use Setono\SyliusCMSPlugin\Admin\EntryPlacements;
use Setono\SyliusCMSPlugin\Admin\PageScopeCondition;
use Setono\SyliusCMSPlugin\Admin\PageScopeSummaries;
use Setono\SyliusCMSPlugin\Admin\SlotOrder;
use Setono\SyliusCMSPlugin\Admin\WinsFor;
use Setono\SyliusCMSPlugin\Admin\WinsWhen;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductKind;
use Setono\SyliusCMSPlugin\Condition\Kind\TaxonKind;
use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Homepage\CMSHomepageChannels;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class EntryPlacementsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    /** @var ObjectProphecy<DeclaredContextInterface> */
    private ObjectProphecy $declaredContext;

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $homepages;

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        // What the snippet's rows declare, unless a test drafts more
        $this->declaredContext = $this->prophesize(DeclaredContextInterface::class);
        $this->declaredContext->toPlace(Argument::type(SnippetInterface::class))->will(static fn (array $arguments): array => $arguments[0] instanceof SnippetInterface ? $arguments[0]->getContext() : []);
        $this->homepages = $this->prophesize(HomepageRepositoryInterface::class);
        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
    }

    /**
     * @test
     */
    public function it_lists_a_snippet_s_placements_in_the_order_the_slots_are_declared_with_each_one_s_place_among_its_slot_s_placements(): void
    {
        $snippet = new Snippet();
        $other = new Snippet();
        $inFooter = new Placement($snippet, 'shop.footer', 4);
        $inTop = new Placement($snippet, 'shop.top', 0);
        $othersInFooter = new Placement($other, 'shop.footer', 1);
        $secondInFooter = new Placement($snippet, 'shop.footer', 7);
        $this->placements->findBySnippet($snippet)->willReturn([$inFooter, $secondInFooter, $inTop]);
        $this->placements->findBySlots(['shop.footer', 'shop.top'])->willReturn([$othersInFooter, $inFooter, $secondInFooter, $inTop]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([
            ['shop.top', 1, 1],
            ['shop.footer', 2, 3],
            ['shop.footer', 3, 3],
        ], array_map(static fn (EntryPlacement $placement): array => [$placement->slot?->identifier, $placement->position, $placement->count], $section->placements));
        self::assertSame($inTop, $section->placements[0]->placement);
    }

    /**
     * @test
     */
    public function it_lists_a_placement_in_a_slot_the_code_no_longer_declares_last_without_its_slot(): void
    {
        $snippet = new Snippet();
        $removed = new Placement($snippet, 'shop.removed', 0);
        $inFooter = new Placement($snippet, 'shop.footer', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$inFooter, $removed]);
        $this->placements->findBySlots(['shop.footer', 'shop.removed'])->willReturn([$inFooter, $removed]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([$inFooter, $removed], array_map(static fn (EntryPlacement $placement): object => $placement->placement, $section->placements));
        self::assertNull($section->placements[1]->slot);
    }

    /**
     * @test
     */
    public function it_offers_the_slots_that_provide_at_least_the_context_the_snippet_needs(): void
    {
        $anyPage = new Snippet();
        $productPages = new Snippet();
        $productPages->setContext(['product']);
        $this->placements->findBySnippet($anyPage)->willReturn([]);
        $this->placements->findBySnippet($productPages)->willReturn([]);
        $this->placements->findBySlots([])->willReturn([]);

        self::assertSame(['shop.top', 'shop.footer', 'store_homepage', 'product.after_price'], self::identifiers($this->entryPlacements()->of($anyPage)->offeredSlots ?? []));
        self::assertSame(['product.after_price'], self::identifiers($this->entryPlacements()->of($productPages)->offeredSlots ?? []));
    }

    /**
     * @test
     */
    public function it_offers_the_slots_that_provide_the_context_the_snippet_s_working_copy_declares_too(): void
    {
        $snippet = new Snippet();
        $this->placements->findBySnippet($snippet)->willReturn([]);
        $this->declaredContext->toPlace($snippet)->willReturn(['product']);

        self::assertSame(['product.after_price'], self::identifiers($this->entryPlacements()->of($snippet)->offeredSlots ?? []));
    }

    /**
     * A page scope takes the kinds that read the host page and whose needs its slot provides
     *
     * @test
     */
    public function it_offers_each_placement_the_condition_kinds_its_page_scope_may_hold_and_words_its_scope(): void
    {
        $snippet = new Snippet();
        $scoped = new Placement($snippet, 'product.after_price', 0);
        $scoped->setPageScope([['kind' => 'product', 'negate' => true, 'params' => ['products' => ['T_SHIRT']]]]);
        $inFooter = new Placement($snippet, 'shop.footer', 0);
        $removed = new Placement($snippet, 'shop.removed', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$scoped, $inFooter, $removed]);
        $this->placements->findBySlots(Argument::any())->willReturn([$scoped, $inFooter, $removed]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([
            ['shop.footer', [], []],
            ['product.after_price', ['product'], [['product', 'setono_sylius_cms.condition_kind.product', 'setono_sylius_cms.builder.is_not_one_of', ['T_SHIRT']]]],
            ['shop.removed', [], []],
        ], array_map(static fn (EntryPlacement $placement): array => [
            $placement->placement->getSlot(),
            $placement->scopeKinds,
            array_map(static fn (PageScopeCondition $condition): array => [$condition->kind, $condition->label, $condition->matches, $condition->values], $placement->pageScope),
        ], $section->placements));
    }

    /**
     * In a capped slot, a placement within the first `cap` wins wherever it matches, one after them wins where fewer
     * than `cap` placements above it match, and one after `cap` catch-alls never wins. In a slot that isn't capped,
     * every placement shows wherever it matches. Each wins for its snippet's audience. See "Placements" in
     * docs/spec/admin.md
     *
     * @test
     */
    public function it_says_who_each_placement_wins_for_by_its_snippet_s_audience_and_the_catch_all_placements_above_it(): void
    {
        $snippet = self::snippetFor(Audience::only('wholesale'));
        $everyone = new Snippet();
        $first = new Placement($snippet, 'shop.top', 0);
        $catchAll = new Placement($everyone, 'shop.top', 1);
        $afterCatchAll = new Placement($snippet, 'shop.top', 2);
        $scoped = new Placement($everyone, 'product.after_price', 0);
        $scoped->setPageScope([['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);
        $afterScoped = new Placement($snippet, 'product.after_price', 1);
        $inFooter = new Placement($snippet, 'shop.footer', 5);
        $removed = new Placement($snippet, 'shop.removed', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$afterScoped, $inFooter, $removed, $first, $afterCatchAll]);
        $this->placements->findBySlots(['product.after_price', 'shop.footer', 'shop.removed', 'shop.top'])->willReturn([$scoped, $afterScoped, $inFooter, $removed, $first, $catchAll, $afterCatchAll]);

        $section = $this->entryPlacements(['shop.top' => 1, 'product.after_price' => 1])->of($snippet);

        self::assertNotNull($section);
        $wholesale = Audience::only('wholesale');
        self::assertEquals([
            [$first, new WinsFor($wholesale, WinsWhen::Matching, 1)],
            [$afterCatchAll, new WinsFor($wholesale, WinsWhen::Never, 1)],
            [$inFooter, new WinsFor($wholesale, WinsWhen::Matching, null)],
            [$afterScoped, new WinsFor($wholesale, WinsWhen::FewerAboveMatch, 1)],
            [$removed, null],
        ], array_map(static fn (EntryPlacement $placement): array => [$placement->placement, $placement->winsFor], $section->placements));
    }

    /**
     * The Order… dialog of each slot the snippet is in, capped or not: every placement in the slot, of every snippet,
     * with the slot's warnings. See "Placements" in docs/spec/admin.md
     *
     * @test
     */
    public function it_gives_each_slot_the_snippet_is_placed_in_its_order_of_every_snippet_s_placements_and_its_warnings(): void
    {
        $snippet = self::snippetFor(Audience::only('wholesale'));
        $other = new Snippet();
        $othersFirst = new Placement($other, 'store_homepage', 0);
        $othersFirst->setPageScope([['kind' => 'removed', 'negate' => false, 'params' => []]]);
        $inHomepage = new Placement($snippet, 'store_homepage', 1);
        $inTop = new Placement($snippet, 'shop.top', 0);
        $othersCatchAll = new Placement($other, 'shop.top', 1);
        $againInTop = new Placement($snippet, 'shop.top', 2);
        $inFooter = new Placement($snippet, 'shop.footer', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$inTop, $againInTop, $inHomepage, $inFooter]);
        $this->placements->findBySlots(['shop.footer', 'shop.top', 'store_homepage'])->willReturn([$inFooter, $inTop, $othersCatchAll, $againInTop, $othersFirst, $inHomepage]);
        $this->channels->findAll()->willReturn([self::channel('WEB', 'Web store'), self::channel('B2B', 'Wholesale')]);
        $this->homepages->findByChannelCodes(['B2B', 'WEB'])->willReturn([new Homepage(new Page(), 'WEB')]);
        $this->channels->findBy(['code' => ['WEB']])->willReturn([self::channel('WEB', 'Web store')]);

        $section = $this->entryPlacements(['shop.top' => 1, 'store_homepage' => 2])->of($snippet);

        self::assertNotNull($section);
        self::assertSame([
            ['shop.top', [$inTop, $othersCatchAll, $againInTop], [1, 2, 3], false, []],
            ['shop.footer', [$inFooter], [1], false, []],
            ['store_homepage', [$othersFirst, $inHomepage], [1, 2], true, ['Web store']],
        ], self::orders($section->slotOrders));
        self::assertSame(['removed'], array_map(static fn (PageScopeCondition $condition): string => $condition->kind, $section->slotOrders[2]->placements[0]->pageScope), "Another snippet's placement has its page scope worded");
    }

    /**
     * A slot that isn't capped shows every placement that matches, so it can't lack a catch-all
     *
     * @test
     */
    public function it_warns_of_no_missing_catch_all_in_a_slot_that_is_not_capped_but_of_a_cms_homepage_hiding_it(): void
    {
        $snippet = self::snippetFor(Audience::only('wholesale'));
        $inHomepage = new Placement($snippet, 'store_homepage', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$inHomepage]);
        $this->placements->findBySlots(['store_homepage'])->willReturn([$inHomepage]);
        $this->channels->findAll()->willReturn([self::channel('WEB', 'Web store')]);
        $this->homepages->findByChannelCodes(['WEB'])->willReturn([new Homepage(new Page(), 'WEB')]);
        $this->channels->findBy(['code' => ['WEB']])->willReturn([self::channel('WEB', 'Web store')]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([['store_homepage', [$inHomepage], [1], false, ['Web store']]], self::orders($section->slotOrders));
    }

    /**
     * Such a placement can only be removed
     *
     * @test
     */
    public function it_gives_no_order_to_a_slot_the_code_no_longer_declares(): void
    {
        $snippet = new Snippet();
        $inRemovedSlot = new Placement($snippet, 'shop.gone', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$inRemovedSlot]);
        $this->placements->findBySlots(['shop.gone'])->willReturn([$inRemovedSlot]);
        $this->homepages->findByChannelCodes(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], $this->entryPlacements()->of($snippet)?->slotOrders);
    }

    /**
     * @test
     */
    public function it_has_no_placements_section_for_an_entry_that_is_not_a_snippet(): void
    {
        self::assertNull($this->entryPlacements()->of(new Page()));
    }

    /**
     * Each order's slot, placements, their positions and warnings
     *
     * @param list<SlotOrder> $orders
     *
     * @return list<array{string, list<object>, list<int>, bool, list<string>}>
     */
    private static function orders(array $orders): array
    {
        return array_map(static fn (SlotOrder $order): array => [
            $order->slot->identifier,
            array_map(static fn (EntryPlacement $placement): object => $placement->placement, $order->placements),
            array_map(static fn (EntryPlacement $placement): int => $placement->position, $order->placements),
            $order->withoutCatchAll,
            $order->hiddenByHomepageIn,
        ], $orders);
    }

    /**
     * @param list<SlotMetadata> $slots
     *
     * @return list<string>
     */
    private static function identifiers(array $slots): array
    {
        return array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $slots);
    }

    /**
     * @param array<string, int> $caps the caps of the slots that have one, by identifier
     */
    private function entryPlacements(array $caps = []): EntryPlacements
    {
        $conditionKinds = Conditions::registry(['product' => new ProductKind(), 'taxon' => new TaxonKind()]);

        return new EntryPlacements(
            $this->placements->reveal(),
            new SlotRegistry([
                'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => $caps['shop.top'] ?? null],
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'store_homepage' => ['label' => 'setono_sylius_cms.slot.store_homepage', 'provides' => [], 'cap' => $caps['store_homepage'] ?? null, 'template_event' => ['name' => 'sylius.shop.homepage', 'priority' => 100]],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => $caps['product.after_price'] ?? null],
            ]),
            $this->declaredContext->reveal(),
            $conditionKinds,
            new PageScopeSummaries(
                $conditionKinds,
                new FieldHandlerLocator(new ServiceLocator([ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler()])),
                new ServiceLocator([]),
                new IdentityTranslator(),
            ),
            new CMSHomepageChannels($this->homepages->reveal(), $this->channels->reveal()),
        );
    }

    private static function snippetFor(Audience $audience): Snippet
    {
        $snippet = new Snippet();
        ChannelsAndAudience::modifier()->setAudience($snippet, $audience);

        return $snippet;
    }

    private static function channel(string $code, string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
