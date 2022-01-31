<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Element\BlockElement;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Twig\Environment;
use Twig\Error\Error;
use Webmozart\Assert\Assert;

/**
 * @implements RendererInterface<BlockInterface>
 */
final class BlockRenderer implements RendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private BlockRepositoryInterface $blockRepository;

    private Environment $twig;

    private bool $debug;

    public function __construct(
        BlockRepositoryInterface $blockRepository,
        Environment $twig,
        bool $debug = false
    ) {
        $this->logger = new NullLogger();
        $this->blockRepository = $blockRepository;
        $this->twig = $twig;
        $this->debug = $debug;
    }

    public function render($element): Response
    {
        if (is_string($element)) {
            $code = $element;
            $element = $this->blockRepository->findOneByCode($code);
            if (null === $element) {
                $this->logger->error(sprintf('The block "%s" is not defined', $code));

                return $this->renderNonExistingBlock($code);
            }
        }

        Assert::isInstanceOf($element, BlockInterface::class);

        try {
            $renderedBlockContent = $this->renderBlockContent($element->getContent() ?? '');
        } catch (Error $exception) {
            $renderedBlockContent = sprintf('<!-- Impossible to render the block "%s" because it contains malformed content. Error: %s -->', (string) $element->getCode(), $exception->getMessage());
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/block.html.twig', [
            'block' => new BlockElement($element, $renderedBlockContent),
        ]), ElementId::fromResource($element));
    }

    /**
     * @throws Error
     */
    private function renderBlockContent(string $blockContent): string
    {
        return $this->twig->render($this->twig->createTemplate($blockContent));
    }

    private function renderNonExistingBlock(string $code): Response
    {
        if (!$this->debug) {
            return Response::empty();
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/block/non_existing.html.twig', [
            'code' => $code,
        ]));
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
