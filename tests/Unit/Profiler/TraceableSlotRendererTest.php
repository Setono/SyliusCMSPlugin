<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Profiler\RenderedSlots;
use Setono\SyliusCMSPlugin\Profiler\SlotTrace;
use Setono\SyliusCMSPlugin\Profiler\TraceableSlotRenderer;
use Setono\SyliusCMSPlugin\Rendering\SlotRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TraceableSlotRendererTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_records_each_slot_it_renders_in_order_with_its_render_time(): void
    {
        $slots = new RenderedSlots();
        $renderer = $this->prophesize(SlotRendererInterface::class);
        $renderer->render('shop.top', [])->willReturn('<div>Top</div>');
        $renderer->render('shop.footer', ['foo' => 'bar'])->will(static function (): string {
            usleep(2000);

            return '';
        });

        $traceable = new TraceableSlotRenderer($renderer->reveal(), $slots, self::requestStack());

        self::assertSame('<div>Top</div>', $traceable->render('shop.top'));
        self::assertSame('', $traceable->render('shop.footer', ['foo' => 'bar']));

        self::assertSame(['shop.top', 'shop.footer'], array_map(static fn (SlotTrace $slot): string => $slot->identifier, $slots->all()));
        self::assertGreaterThanOrEqual(2.0, $slots->all()[1]->duration);
        self::assertNull($slots->current(), 'No slot is rendering any more');
    }

    /**
     * Such as taxon.header, which Sylius renders in a sub-request of its own
     *
     * @test
     */
    public function it_records_the_route_of_the_sub_request_a_slot_renders_in(): void
    {
        $slots = new RenderedSlots();
        $requestStack = self::requestStack();
        $subRequest = Request::create('/en_US/taxons/t-shirts/partial');
        $subRequest->attributes->set('_route', 'sylius_shop_partial_taxon_show_by_slug');
        $requestStack->push($subRequest);

        (new TraceableSlotRenderer($this->renderer(), $slots, $requestStack))->render('taxon.header');

        self::assertSame('sylius_shop_partial_taxon_show_by_slug', $slots->all()[0]->subRequest);
    }

    /**
     * @test
     */
    public function it_records_no_sub_request_for_a_slot_in_the_main_request(): void
    {
        $slots = new RenderedSlots();

        (new TraceableSlotRenderer($this->renderer(), $slots, self::requestStack()))->render('shop.top');

        self::assertNull($slots->all()[0]->subRequest);
    }

    /**
     * @test
     */
    public function it_ends_a_slot_that_throws(): void
    {
        $slots = new RenderedSlots();
        $renderer = $this->prophesize(SlotRendererInterface::class);
        $renderer->render(Argument::cetera())->willThrow(new \RuntimeException('Boom'));

        try {
            (new TraceableSlotRenderer($renderer->reveal(), $slots, self::requestStack()))->render('shop.top');
            self::fail('The exception was thrown');
        } catch (\RuntimeException) {
        }

        self::assertNull($slots->current());
        self::assertNotNull($slots->all()[0]->duration);
    }

    /**
     * Such as a slot a snippet's block template renders with cms_slot()
     *
     * @test
     */
    public function it_records_a_slot_inside_another_and_returns_to_the_outer_one(): void
    {
        $slots = new RenderedSlots();
        $renderer = $this->prophesize(SlotRendererInterface::class);
        $traceable = new TraceableSlotRenderer($renderer->reveal(), $slots, self::requestStack());
        $renderer->render('app.inner', [])->willReturn('');
        $renderer->render('shop.footer', [])->will(static function () use ($traceable, $slots): string {
            $traceable->render('app.inner');

            return (string) $slots->current()?->identifier;
        });

        self::assertSame('shop.footer', $traceable->render('shop.footer'));
        self::assertSame(['shop.footer', 'app.inner'], array_map(static fn (SlotTrace $slot): string => $slot->identifier, $slots->all()));
    }

    private function renderer(): SlotRendererInterface
    {
        $renderer = $this->prophesize(SlotRendererInterface::class);
        $renderer->render(Argument::cetera())->willReturn('');

        return $renderer->reveal();
    }

    private static function requestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/en_US/products/t-shirt'));

        return $requestStack;
    }
}
