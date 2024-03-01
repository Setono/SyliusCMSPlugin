<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Webmozart\Assert\Assert;

/**
 * @implements TwigGeneratorInterface<PageInterface>
 */
final class PageTwigGenerator implements TwigGeneratorInterface
{
    public function __construct(private readonly ParserInterface $parser, private readonly RendererInterface $renderer)
    {
    }

    /**
     * @param PageInterface $element
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        Assert::isInstanceOf($element, PageInterface::class);

        return $this->renderer->render($this->parser->parse((string) $element->getContent()));
    }
}
