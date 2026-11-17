<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Button;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Preview\UnresolvedLinkNotices;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\Link;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UnresolvedLinkNoticesTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_follows_a_block_whose_link_does_not_resolve_with_a_notice_naming_the_field_in_the_builders_preview(): void
    {
        $html = $this->notices()->render(self::button(null), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringStartsWith('<div data-cms-unresolved-link="link" role="note" style="', $html);
        self::assertStringEndsWith('">&lt;Link&gt; isn&#039;t shown</div>', $html);
    }

    /**
     * @test
     */
    public function it_leaves_a_block_whose_link_resolves_as_it_is(): void
    {
        $html = $this->notices('<a class="cms-button">Red T-shirt</a>')->render(self::button(new ResolvedLink('product', 'T_SHIRT', '/en_US/products/red-t-shirt', 'Red T-shirt')), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertSame('<a class="cms-button">Red T-shirt</a>', $html);
    }

    /**
     * @test
     */
    public function it_leaves_a_block_without_a_link_as_it_is(): void
    {
        $empty = new BlockView(self::KEY, 'button', new Button(), null, []);

        self::assertSame('', $this->notices()->render($empty, $this->context(RenderContext::PREVIEW_MODE_BUILDER)));
        self::assertSame('<h2>Hi</h2>', $this->notices('<h2>Hi</h2>')->render(new BlockView(self::KEY, 'heading', new Heading(), null, []), $this->context(RenderContext::PREVIEW_MODE_BUILDER)));
    }

    /**
     * @test
     */
    public function it_follows_a_block_with_rich_text_holding_a_link_that_does_not_resolve_with_a_notice_naming_the_field(): void
    {
        $html = $this->notices('<div class="cms-rich-text"><p>Meet the team</p></div>')->render(self::richText(1), $this->context(RenderContext::PREVIEW_MODE_BUILDER));

        self::assertStringStartsWith('<div class="cms-rich-text"><p>Meet the team</p></div><div data-cms-unresolved-link="content" role="note" style="', $html);
        self::assertStringEndsWith('">A link in Content shows as plain text</div>', $html);
    }

    /**
     * @test
     */
    public function it_leaves_a_block_with_rich_text_whose_links_resolve_as_it_is(): void
    {
        self::assertSame('<p>Hi</p>', $this->notices('<p>Hi</p>')->render(self::richText(0), $this->context(RenderContext::PREVIEW_MODE_BUILDER)));
        self::assertSame('', $this->notices()->render(new BlockView(self::KEY, 'rich_text', new RichText(), null, []), $this->context(RenderContext::PREVIEW_MODE_BUILDER)));
    }

    /**
     * @test
     */
    public function it_gives_shoppers_and_share_links_no_notice(): void
    {
        self::assertSame('', $this->notices()->render(self::button(null), $this->context(null)));
        self::assertSame('', $this->notices()->render(self::button(null), $this->context(RenderContext::PREVIEW_MODE_SHARE)));
        self::assertSame('', $this->notices()->render(self::richText(1), $this->context(RenderContext::PREVIEW_MODE_SHARE)));
    }

    private function notices(string $html = ''): UnresolvedLinkNotices
    {
        $renderer = $this->prophesize(BlockRendererInterface::class);
        $renderer->render(Argument::cetera())->willReturn($html);

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('setono_sylius_cms.field.button.link')->willReturn('<Link>');
        $translator->trans('setono_sylius_cms.preview.unresolved_link', ['%field%' => '<Link>'])->willReturn('<Link> isn\'t shown');
        $translator->trans('setono_sylius_cms.field.rich_text.content')->willReturn('Content');
        $translator->trans('setono_sylius_cms.preview.unresolved_rich_text_link', ['%field%' => 'Content'])->willReturn('A link in Content shows as plain text');

        return new UnresolvedLinkNotices(
            $renderer->reveal(),
            new BlockTypeRegistry(['button' => Button::class, 'heading' => Heading::class, 'rich_text' => RichText::class], new FieldReader()),
            $translator->reveal(),
        );
    }

    /**
     * A Rich text block whose content holds the number of links that don't resolve
     */
    private static function richText(int $unresolvedLinks): BlockView
    {
        $richText = new RichText();
        $richText->content = new RichTextValue(['type' => 'doc', 'content' => []], '', $unresolvedLinks);

        return new BlockView(self::KEY, 'rich_text', $richText, null, []);
    }

    private static function button(?ResolvedLink $resolved): BlockView
    {
        $button = new Button();
        $button->link = new Link('product', 'T_SHIRT', null, null, $resolved);

        return new BlockView(self::KEY, 'button', $button, null, []);
    }

    /**
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     */
    private function context(?string $previewMode): RenderContext
    {
        return Rendering::context(previewMode: $previewMode);
    }
}
