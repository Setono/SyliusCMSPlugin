<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\TwigBlockRenderer;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigBlockRendererTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_renders_the_block_type_template_with_the_block_template_variables(): void
    {
        $twig = new Environment(new ArrayLoader([
            'teaser.html.twig' => '{{ block.heading }}|{{ view.key }}|{{ children|length }}|{{ context|keys|join(",") }}|{{ _cms_render_context.localeCode }}',
        ]), ['strict_variables' => true]);

        $teaser = new Teaser();
        $teaser->heading = 'Hello';
        $child = new BlockView('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b', 'teaser', new Teaser(), null, []);
        $view = new BlockView('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a', 'teaser', $teaser, null, [$child]);
        $context = new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'da_DK', 'DKK', null, '@guest', new \DateTimeImmutable(), ['entry' => new Page()]);

        $html = (new TwigBlockRenderer($twig, new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader())))->render($view, $context);

        self::assertSame('Hello|0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a|1|entry|da_DK', $html);
    }
}
