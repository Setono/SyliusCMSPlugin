<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\PageScopeMismatch;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * What developers break in code is kept but not rendered, and flagged: a page-scope condition of a kind the code no
 * longer has, or one whose kind needs context the slot no longer provides. See "Mismatches" in docs/spec/slots.md
 */
final class PageScopeMismatchTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_each_page_scope_condition_of_a_kind_the_code_no_longer_has_as_a_warning_fixed_in_the_placements_section(): void
    {
        $snippet = new Snippet();
        $scoped = new Placement($snippet, 'product.after_price', 0);
        $scoped->setPageScope([
            ['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]],
            ['kind' => 'brand', 'negate' => true, 'params' => []],
        ]);
        $footer = new Placement($snippet, 'shop.footer', 0);
        $footer->setPageScope([['kind' => 'season', 'negate' => false, 'params' => []]]);

        self::assertEquals([
            self::problem($snippet, new TranslatableMessage('setono_sylius_cms.problem.page_scope_removed_kind', ['%slot%' => new TranslatableMessage('setono_sylius_cms.slot.product.after_price'), '%kind%' => 'brand'])),
            self::problem($snippet, new TranslatableMessage('setono_sylius_cms.problem.page_scope_removed_kind', ['%slot%' => new TranslatableMessage('setono_sylius_cms.slot.shop.footer'), '%kind%' => 'season'])),
        ], [...$this->kind([$scoped, $footer])->findIn($snippet)]);
    }

    /**
     * Such as a project's slot that stopped providing the product
     *
     * @test
     */
    public function it_finds_each_page_scope_condition_whose_kind_needs_context_the_slot_no_longer_provides(): void
    {
        $snippet = new Snippet();
        $placement = new Placement($snippet, 'shop.footer', 0);
        $placement->setPageScope([['kind' => 'product_in_taxon', 'negate' => true, 'params' => ['taxons' => ['SHOES']]]]);

        self::assertEquals([
            self::problem($snippet, new TranslatableMessage('setono_sylius_cms.problem.page_scope_unprovided', [
                '%slot%' => new TranslatableMessage('setono_sylius_cms.slot.shop.footer'),
                '%kind%' => new TranslatableMessage('setono_sylius_cms.condition_kind.product_in_taxon'),
                '%context%' => new TranslatableMessage('setono_sylius_cms.context_key.product'),
            ])),
        ], [...$this->kind([$placement])->findIn($snippet)]);
    }

    /**
     * A placement in a removed slot is flagged as such, and a condition that isn't one is never stored
     *
     * @test
     */
    public function it_finds_nothing_for_scopes_that_fit_their_slots_a_removed_slot_an_entry_that_is_not_a_snippet_or_what_is_not_a_condition(): void
    {
        $snippet = new Snippet();
        $fits = new Placement($snippet, 'product.after_price', 0);
        $fits->setPageScope([['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]]]);
        $removedSlot = new Placement($snippet, 'shop.gone', 0);
        $removedSlot->setPageScope([['kind' => 'brand', 'negate' => false, 'params' => []]]);
        $unscoped = new Placement($snippet, 'shop.footer', 0);
        $malformed = new Placement($snippet, 'shop.footer', 1);
        $malformed->setPageScope([['negate' => false]]);

        self::assertSame([], [...$this->kind([$fits, $removedSlot, $unscoped, $malformed])->findIn($snippet)]);
        self::assertSame([], [...$this->kind([])->findIn(new Page())]);
    }

    private static function problem(Snippet $snippet, TranslatableMessage $message): Problem
    {
        return new Problem('page_scope_mismatch', Severity::Warning, $snippet, $message, Fix::inPlacements($snippet));
    }

    /**
     * @param list<Placement> $placements
     */
    private function kind(array $placements): PageScopeMismatch
    {
        $repository = $this->prophesize(PlacementRepositoryInterface::class);
        $repository->findBySnippet(\Prophecy\Argument::any())->willReturn($placements);

        return new PageScopeMismatch(
            $repository->reveal(),
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            Conditions::registry(['product_in_taxon' => new ProductInTaxonKind()]),
            new ContextKeyRegistry(['product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product']]),
        );
    }
}
