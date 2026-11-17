<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Profiler\RecordedFailure;
use Setono\SyliusCMSPlugin\Profiler\RenderedSlots;
use Setono\SyliusCMSPlugin\Profiler\TraceableFailureHandler;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\FailureHandlerInterface;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Twig\Error\RuntimeError;

final class TraceableFailureHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const SNIPPET = '0193a5be-0000-7000-8000-000000000001';

    private const BLOCK = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_records_a_block_that_threw_in_the_slot_rendering_now_and_hands_it_on(): void
    {
        $exception = new \RuntimeException('Boom');
        $view = self::view(self::BLOCK, 'broken');
        $handler = $this->prophesize(FailureHandlerInterface::class);
        $handler->blockFailed($view, $exception)->shouldBeCalledOnce();
        $slots = new RenderedSlots();
        $slot = $slots->start('shop.footer');

        (new TraceableFailureHandler($handler->reveal(), $slots))->blockFailed($view, $exception);

        self::assertEquals([new RecordedFailure(self::SNIPPET, self::BLOCK, 'broken', null, 'Boom')], $slot->failures);
    }

    /**
     * @test
     */
    public function it_records_a_snippet_that_threw_in_the_slot_rendering_now(): void
    {
        $exception = new RuntimeError('Twig\'s wrapper', previous: new \LogicException('No upgrader for version 7'));
        $placement = new PlacementData('0193a5be-0000-7000-8000-0000000000aa', self::SNIPPET, 'shop.footer', [], 0);
        $handler = $this->prophesize(FailureHandlerInterface::class);
        $handler->snippetFailed($placement, $exception)->shouldBeCalledOnce();
        $slots = new RenderedSlots();
        $slot = $slots->start('shop.footer');

        (new TraceableFailureHandler($handler->reveal(), $slots))->snippetFailed($placement, $exception);

        self::assertEquals([new RecordedFailure(self::SNIPPET, null, null, '0193a5be-0000-7000-8000-0000000000aa', 'No upgrader for version 7')], $slot->failures);
    }

    /**
     * Such as in an entry's own tree
     *
     * @test
     */
    public function it_records_a_block_that_threw_outside_any_slot_as_such(): void
    {
        $handler = $this->prophesize(FailureHandlerInterface::class);
        $slots = new RenderedSlots();

        (new TraceableFailureHandler($handler->reveal(), $slots))->blockFailed(self::view(self::BLOCK, 'broken'), new \RuntimeException('Boom'));

        self::assertEquals([new RecordedFailure(self::SNIPPET, self::BLOCK, 'broken', null, 'Boom')], $slots->failuresOutsideSlots());
    }

    /**
     * In dev, the handler throws it on, and the blocks it sits in throw it too, wrapped by Twig. Only the block that
     * threw it first threw
     *
     * @test
     */
    public function it_records_only_the_block_that_threw_first_when_the_exception_passes_up_through_the_blocks_it_sits_in(): void
    {
        $exception = new \RuntimeException('Boom');
        $handler = $this->prophesize(FailureHandlerInterface::class);
        $handler->blockFailed(Argument::cetera())->will(static function (array $arguments): void {
            throw $arguments[1];
        });
        $slots = new RenderedSlots();
        $traceable = new TraceableFailureHandler($handler->reveal(), $slots);

        try {
            $traceable->blockFailed(self::view(self::BLOCK, 'broken'), $exception);
        } catch (\RuntimeException) {
        }

        try {
            $traceable->blockFailed(self::view('0193a5be-0000-7000-8000-000000000003', 'group'), new RuntimeError('Twig\'s wrapper', previous: $exception));
            self::fail('The exception was thrown on');
        } catch (RuntimeError) {
        }

        self::assertEquals([new RecordedFailure(self::SNIPPET, self::BLOCK, 'broken', null, 'Boom')], $slots->failuresOutsideSlots());
    }

    private static function view(string $key, string $type): BlockView
    {
        return new BlockView($key, $type, new \stdClass(), null, [], entry: self::SNIPPET);
    }
}
