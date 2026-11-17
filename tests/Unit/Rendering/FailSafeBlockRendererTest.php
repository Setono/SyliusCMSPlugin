<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\FailSafeBlockRenderer;
use Setono\SyliusCMSPlugin\Rendering\FailureHandlerInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

final class FailSafeBlockRendererTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_renders_a_block_that_does_not_throw_as_it_is(): void
    {
        $view = self::view();
        $context = Rendering::context();
        $renderer = $this->prophesize(BlockRendererInterface::class);
        $renderer->render($view, $context)->willReturn('<h2>Hello</h2>');
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed(Argument::cetera())->shouldNotBeCalled();

        self::assertSame('<h2>Hello</h2>', (new FailSafeBlockRenderer($renderer->reveal(), $failures->reveal()))->render($view, $context));
    }

    /**
     * @test
     */
    public function it_hands_a_block_that_throws_to_the_failure_handler_and_renders_nothing_of_it(): void
    {
        $view = self::view();
        $exception = new \RuntimeException('Boom');
        $renderer = $this->prophesize(BlockRendererInterface::class);
        $renderer->render(Argument::cetera())->willThrow($exception);
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed($view, $exception)->shouldBeCalledOnce();

        self::assertSame('', (new FailSafeBlockRenderer($renderer->reveal(), $failures->reveal()))->render($view, Rendering::context()));
    }

    /**
     * In dev, the handler throws it on
     *
     * @test
     */
    public function it_lets_what_the_failure_handler_throws_through(): void
    {
        $exception = new \RuntimeException('Boom');
        $renderer = $this->prophesize(BlockRendererInterface::class);
        $renderer->render(Argument::cetera())->willThrow($exception);
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed(Argument::cetera())->willThrow($exception);

        $this->expectExceptionObject($exception);

        (new FailSafeBlockRenderer($renderer->reveal(), $failures->reveal()))->render(self::view(), Rendering::context());
    }

    /**
     * The builder's preview shows a block's error on the block (Preview\PreviewBlockRenderer), which then never gets
     * here
     *
     * @test
     */
    public function it_leaves_the_builders_preview_to_show_a_blocks_error_on_the_block(): void
    {
        $exception = new \RuntimeException('Boom');
        $renderer = $this->prophesize(BlockRendererInterface::class);
        $renderer->render(Argument::cetera())->willThrow($exception);
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed(Argument::cetera())->shouldNotBeCalled();

        $this->expectExceptionObject($exception);

        (new FailSafeBlockRenderer($renderer->reveal(), $failures->reveal()))->render(self::view(), Rendering::context(previewMode: RenderContext::PREVIEW_MODE_BUILDER));
    }

    private static function view(): BlockView
    {
        return new BlockView('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', new \stdClass(), null, [], entry: '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40');
    }
}
