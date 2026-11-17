<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Context\PlacementDependencies;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A snippet's placement holds it to the context keys its slot provides. Its page scope's conditions read the context
 * the slot provides, not what the snippet declares, so a snippet shown on any page can be scoped to products in a taxon
 * in a product page's slot. See "Snippets and their context" and "Page scopes" in docs/spec/slots.md
 */
final class PlacementDependenciesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_each_placement_of_a_snippet_with_what_its_slot_provides_whatever_its_page_scope_needs(): void
    {
        $snippet = new Snippet();
        $scoped = new Placement($snippet, 'product.after_price', 0);
        $scoped->setPageScope([['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]]]);
        $placements = $this->prophesize(PlacementRepositoryInterface::class);
        $placements->findBySnippet($snippet)->willReturn([$scoped, new Placement($snippet, 'shop.footer', 1), new Placement($snippet, 'shop.removed', 0)]);

        $placement = static fn (TranslatableMessage|string $slot): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.context.placement', ['%slot%' => $slot], 'validators');

        self::assertEquals([
            new ContextDependency($placement(new TranslatableMessage('setono_sylius_cms.slot.product.after_price')), [], ['product']),
            new ContextDependency($placement(new TranslatableMessage('setono_sylius_cms.slot.shop.footer')), [], []),
            // A slot the code no longer declares is flagged on its own, and limits nothing
            new ContextDependency($placement('shop.removed'), [], null),
        ], [...self::finder($placements->reveal())->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_an_entry_that_is_not_a_snippet(): void
    {
        $placements = $this->prophesize(PlacementRepositoryInterface::class);
        $placements->findBySnippet(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...self::finder($placements->reveal())->findIn(new Page())]);
    }

    private static function finder(PlacementRepositoryInterface $placements): PlacementDependencies
    {
        return new PlacementDependencies(
            $placements,
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
        );
    }
}
