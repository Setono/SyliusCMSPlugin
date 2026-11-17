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
use Setono\SyliusCMSPlugin\Condition\Kind\ProductKind;
use Setono\SyliusCMSPlugin\Condition\Kind\TaxonKind;
use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class EntryPlacementsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    /** @var ObjectProphecy<DeclaredContextInterface> */
    private ObjectProphecy $declaredContext;

    protected function setUp(): void
    {
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        // What the snippet's rows declare, unless a test drafts more
        $this->declaredContext = $this->prophesize(DeclaredContextInterface::class);
        $this->declaredContext->toPlace(Argument::type(SnippetInterface::class))->will(static fn (array $arguments): array => $arguments[0] instanceof SnippetInterface ? $arguments[0]->getContext() : []);
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
            ['shop.top', 1, 1, true, true],
            ['shop.footer', 2, 3, false, false],
            ['shop.footer', 3, 3, false, true],
        ], array_map(static fn (EntryPlacement $placement): array => [$placement->slot?->identifier, $placement->position, $placement->count, $placement->first, $placement->last], $section->placements));
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

        self::assertSame(['shop.top', 'shop.footer', 'product.after_price'], self::identifiers($this->entryPlacements()->of($anyPage)->offeredSlots ?? []));
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
     * @test
     */
    public function it_has_no_placements_section_for_an_entry_that_is_not_a_snippet(): void
    {
        self::assertNull($this->entryPlacements()->of(new Page()));
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

    private function entryPlacements(): EntryPlacements
    {
        $conditionKinds = Conditions::registry(['product' => new ProductKind(), 'taxon' => new TaxonKind()]);

        return new EntryPlacements(
            $this->placements->reveal(),
            new SlotRegistry([
                'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => null],
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            $this->declaredContext->reveal(),
            $conditionKinds,
            new PageScopeSummaries(
                $conditionKinds,
                new FieldHandlerLocator(new ServiceLocator([ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler()])),
                new ServiceLocator([]),
                new IdentityTranslator(),
            ),
        );
    }
}
