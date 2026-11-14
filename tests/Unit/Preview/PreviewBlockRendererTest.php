<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewBlockRenderer;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Error\RuntimeError;

final class PreviewBlockRendererTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function in_the_builders_preview_it_brackets_a_blocks_output_with_markers_that_carry_its_key(): void
    {
        $html = $this->renderer('<h2 class="cms-heading">Hello</h2>')->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertSame(
            '<!--cms:block ' . self::KEY . ' heading--><h2 class="cms-heading">Hello</h2><!--/cms:block ' . self::KEY . '-->',
            $html,
        );
    }

    /**
     * @test
     */
    public function shoppers_get_no_markers(): void
    {
        $html = $this->renderer('<h2 class="cms-heading">Hello</h2>')->render($this->view(), $this->context(null));

        self::assertSame('<h2 class="cms-heading">Hello</h2>', $html);
    }

    /**
     * @test
     */
    public function share_links_get_no_markers(): void
    {
        $html = $this->renderer('<h2 class="cms-heading">Hello</h2>')->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_SHARE));

        self::assertSame('<h2 class="cms-heading">Hello</h2>', $html);
    }

    /**
     * @test
     */
    public function in_the_builders_preview_a_block_that_throws_shows_its_error_message_on_that_block(): void
    {
        $html = $this->throwingRenderer(new \RuntimeException('The <product> is gone'))->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringStartsWith('<!--cms:block ' . self::KEY . ' heading--><div data-cms-block-error role="alert"', $html);
        self::assertStringEndsWith('>The &lt;product&gt; is gone</div><!--/cms:block ' . self::KEY . '-->', $html);
    }

    /**
     * @test
     */
    public function it_shows_the_message_of_the_exception_a_template_threw_rather_than_twigs_wrapper(): void
    {
        $thrown = new RuntimeError('An exception has been thrown during the rendering of a template ("Boom").', previous: new \LogicException('Boom'));

        $html = $this->throwingRenderer($thrown)->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringContainsString('>Boom</div>', $html);
    }

    /**
     * @test
     */
    public function it_shows_the_message_of_an_exception_that_wraps_another_when_twig_did_not_wrap_it(): void
    {
        $thrown = new \RuntimeException('The product list could not be loaded', previous: new \LogicException('Connection refused'));

        $html = $this->throwingRenderer($thrown)->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringContainsString('>The product list could not be loaded</div>', $html);
    }

    /**
     * @test
     */
    public function it_shows_twigs_own_message_when_twig_itself_failed(): void
    {
        $html = $this->throwingRenderer(new RuntimeError('Variable "nope" does not exist.'))->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringContainsString('>Variable &quot;nope&quot; does not exist.</div>', $html);
    }

    /**
     * @test
     */
    public function it_logs_the_error_with_the_blocks_key_and_type(): void
    {
        $exception = new \RuntimeException('Boom');
        $logger = $this->prophesize(LoggerInterface::class);

        $this->throwingRenderer($exception, $logger->reveal())->render($this->view(), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        $logger->error('The block {key} of type {type} could not be rendered in the preview: {message}', [
            'key' => self::KEY,
            'type' => 'heading',
            'message' => 'Boom',
            'exception' => $exception,
        ])->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function outside_the_builders_preview_the_exception_is_left_to_the_shop(): void
    {
        $exception = new \RuntimeException('Boom');
        $renderer = $this->throwingRenderer($exception);

        $this->expectExceptionObject($exception);

        $renderer->render($this->view(), $this->context(null));
    }

    private function renderer(string $output): PreviewBlockRenderer
    {
        $inner = $this->prophesize(BlockRendererInterface::class);
        $inner->render(Argument::type(BlockView::class), Argument::type(RenderContext::class))->willReturn($output);

        return new PreviewBlockRenderer($inner->reveal());
    }

    private function throwingRenderer(\Throwable $exception, ?LoggerInterface $logger = null): PreviewBlockRenderer
    {
        $inner = $this->prophesize(BlockRendererInterface::class);
        $inner->render(Argument::type(BlockView::class), Argument::type(RenderContext::class))->willThrow($exception);

        return new PreviewBlockRenderer($inner->reveal(), $logger ?? $this->prophesize(LoggerInterface::class)->reveal());
    }

    private function view(): BlockView
    {
        return new BlockView(self::KEY, 'heading', new \stdClass(), null, []);
    }

    /**
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     */
    private function context(?string $previewMode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable('2026-11-14 10:00:00'), [], $previewMode);
    }
}
