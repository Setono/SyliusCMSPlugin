<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownSlotException;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Rendering\SlotRenderer;
use Setono\SyliusCMSPlugin\Rendering\VisibleSnippetsInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SlotRendererTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RenderContextFactoryInterface> */
    private ObjectProphecy $renderContextFactory;

    /** @var ObjectProphecy<PlacementProviderInterface> */
    private ObjectProphecy $placementProvider;

    /** @var ObjectProphecy<VisibleSnippetsInterface> */
    private ObjectProphecy $visibleSnippets;

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    private RenderContext $context;

    protected function setUp(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $this->context = Rendering::context(channel: $channel);

        $this->renderContextFactory = $this->prophesize(RenderContextFactoryInterface::class);
        $this->renderContextFactory->create(Argument::any())->willReturn($this->context);
        $this->placementProvider = $this->prophesize(PlacementProviderInterface::class);
        $this->visibleSnippets = $this->prophesize(VisibleSnippetsInterface::class);
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
    }

    /**
     * @test
     */
    public function it_renders_the_trees_of_the_snippets_placed_in_the_slot_whose_snippets_are_visible_in_editor_order_inside_its_wrapper(): void
    {
        $about = $this->snippet('About us');
        $hours = $this->snippet('Opening hours');
        $hidden = $this->snippet('Unpublished');
        $elsewhere = $this->snippet('Elsewhere');
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([
            self::placement('shop.footer', $about, 2),
            self::placement('shop.footer', $hidden, 0),
            self::placement('shop.footer', $hours, 1),
            self::placement('shop.top', $elsewhere, 0),
        ]);
        $this->visibleSnippets->get([$hidden->getUuid(), $hours->getUuid(), $about->getUuid()], $this->context)
            ->willReturn([$about->getUuid() => $about, $hours->getUuid() => $hours]);

        self::assertSame('<div class="cms-slot cms-slot--shop-footer">[Opening hours][About us]</div>', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_orders_placements_at_the_same_position_as_the_provider_lists_them(): void
    {
        $first = $this->snippet('First');
        $second = $this->snippet('Second');
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([
            self::placement('shop.footer', $first, 1),
            self::placement('shop.footer', $second, 1),
        ]);
        $this->visibleSnippets->get(Argument::any(), $this->context)->willReturn([$second->getUuid() => $second, $first->getUuid() => $first]);

        self::assertSame('<div class="cms-slot cms-slot--shop-footer">[First][Second]</div>', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_shows_a_snippet_placed_twice_in_the_slot_twice(): void
    {
        $snippet = $this->snippet('Twice');
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([
            self::placement('shop.footer', $snippet, 0),
            self::placement('shop.footer', $snippet, 1),
        ]);
        $this->visibleSnippets->get([$snippet->getUuid()], $this->context)->willReturn([$snippet->getUuid() => $snippet]);

        self::assertSame('<div class="cms-slot cms-slot--shop-footer">[Twice][Twice]</div>', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_prints_nothing_not_even_the_wrapper_for_a_slot_without_placements(): void
    {
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([self::placement('shop.top', $this->snippet('Elsewhere'), 0)]);
        $this->visibleSnippets->get(Argument::cetera())->shouldNotBeCalled();

        self::assertSame('', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_prints_nothing_when_no_snippet_placed_in_the_slot_is_visible(): void
    {
        $snippet = $this->snippet('Unpublished');
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([self::placement('shop.footer', $snippet, 0)]);
        $this->visibleSnippets->get([$snippet->getUuid()], $this->context)->willReturn([]);

        self::assertSame('', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_prints_nothing_when_the_visible_snippets_have_no_blocks_that_show(): void
    {
        $empty = $this->snippet(null);
        $unknown = $this->snippet(null, new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40', 'gone', 1));
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([
            self::placement('shop.footer', $empty, 0),
            self::placement('shop.footer', $unknown, 1),
        ]);
        $this->visibleSnippets->get(Argument::any(), $this->context)->willReturn([$empty->getUuid() => $empty, $unknown->getUuid() => $unknown]);

        self::assertSame('', $this->renderer()->render('shop.footer'));
    }

    /**
     * @test
     */
    public function it_loads_the_channel_s_placements_once_for_every_slot_it_renders_until_it_is_reset(): void
    {
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([])->shouldBeCalledTimes(2);
        $renderer = $this->renderer();

        $renderer->render('shop.top');
        $renderer->render('shop.footer');
        $renderer->render('shop.top');
        $renderer->reset();
        $renderer->render('shop.footer');
    }

    /**
     * @test
     */
    public function it_gives_the_snippets_the_context_keys_the_slot_provides_of_those_it_is_passed(): void
    {
        $product = new Product();
        $snippet = $this->snippet('Size guide');
        $this->renderContextFactory->create(['product' => $product])->willReturn($this->context)->shouldBeCalled();
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([self::placement('product.after_price', $snippet, 0)]);
        $this->visibleSnippets->get([$snippet->getUuid()], $this->context)->willReturn([$snippet->getUuid() => $snippet]);

        $html = $this->renderer()->render('product.after_price', ['product' => $product, 'taxon' => new Taxon(), 'title' => 'Not an object', '_cms_slot' => 'product.after_price']);

        self::assertSame('<div class="cms-slot cms-slot--product-after-price">[Size guide]</div>', $html);
    }

    /**
     * @test
     */
    public function it_renders_the_snippets_in_a_slot_of_the_builder_s_preview_as_shoppers_see_them_with_its_shown_to_and_date(): void
    {
        $date = new \DateTimeImmutable('2026-12-01 10:00:00');
        $preview = Rendering::context(channel: $this->context->channel, previewMode: RenderContext::PREVIEW_MODE_BUILDER, now: $date, customerGroup: 'wholesale');
        $this->renderContextFactory->create([])->willReturn($preview);
        $snippet = $this->snippet('Sale');
        $this->placementProvider->getPlacements($this->context->channel)->willReturn([self::placement('shop.footer', $snippet, 0)]);
        $this->visibleSnippets->get([$snippet->getUuid()], $preview)->willReturn([$snippet->getUuid() => $snippet]);

        $html = $this->renderer('{{ _cms_render_context.previewMode ?? "shop" }}|{{ _cms_render_context.customerGroup }}|{{ _cms_render_context.now|date("Y-m-d") }}')->render('shop.footer');

        self::assertSame('shop|wholesale|2026-12-01', $html);
    }

    /**
     * @test
     */
    public function it_throws_for_a_slot_the_code_does_not_declare(): void
    {
        $this->expectException(UnknownSlotException::class);

        $this->renderer()->render('shop.gone');
    }

    private function renderer(string $template = '<div class="{{ slot.wrapperClass }}">{% for placed in snippets %}{% for view in placed.blocks %}[{{ view.block.text }}]{% endfor %}{% endfor %}</div>'): SlotRenderer
    {
        $handlers = Conditions::fieldHandlers();
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class], new FieldReader());
        $referenceLoader = new ReferenceLoader(new ServiceLocator([]));

        return new SlotRenderer(
            new SlotRegistry([
                'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => null],
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            $this->renderContextFactory->reveal(),
            $this->placementProvider->reveal(),
            $this->visibleSnippets->reveal(),
            $this->upgrader->reveal(),
            new BlockViewBuilder(
                $blockTypes,
                new BlockHydrator($handlers, new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US')))),
                Conditions::matcher(),
                new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers),
                $referenceLoader,
            ),
            $referenceLoader,
            new Environment(new ArrayLoader([SlotRenderer::TEMPLATE => $template])),
        );
    }

    /**
     * A snippet whose upgraded tree is a heading with the text, or the block given, or no blocks for neither
     */
    private function snippet(?string $heading, ?StoredBlock $block = null): SnippetInterface
    {
        $snippet = new Snippet();

        $blocks = [];
        if (null !== $heading) {
            $blocks[] = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1, fields: ['text' => ['en_US' => $heading]]);
        }

        if (null !== $block) {
            $blocks[] = $block;
        }

        $this->upgrader->readBlockTree($snippet)->willReturn(new StoredTree(null, $blocks));

        return $snippet;
    }

    private static function placement(string $slot, SnippetInterface $snippet, int $position): PlacementData
    {
        return new PlacementData(sprintf('0193a5be-71b0-7a3c-9d4e-%012d', random_int(0, 999_999_999)), $snippet->getUuid(), $slot, [], $position);
    }
}
