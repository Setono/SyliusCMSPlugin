<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Twig\Environment;

final class BlockRenderer implements BlockRendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private BlockRepositoryInterface $blockRepository;

    private Environment $twig;

    private ElementStackInterface $elementStack;

    private bool $debug;

    public function __construct(
        BlockRepositoryInterface $blockRepository,
        Environment $twig,
        ElementStackInterface $elementStack,
        bool $debug = false
    ) {
        $this->logger = new NullLogger();
        $this->blockRepository = $blockRepository;
        $this->twig = $twig;
        $this->elementStack = $elementStack;
        $this->debug = $debug;
    }

    public function render($block): string
    {
        if (is_string($block)) {
            $code = $block;
            $block = $this->blockRepository->findOneByCode($code);
            if (null === $block) {
                $this->logger->error(sprintf('The block "%s" is not defined', $code));

                return $this->renderNonExistingBlock($code);
            }
        }

        $this->elementStack->push($block);

        return $this->twig->render('@SetonoSyliusCMSPlugin/block.html.twig', [
            'block' => $block,
        ]);
    }

    private function renderNonExistingBlock(string $code): string
    {
        if (!$this->debug) {
            return '';
        }

        return $this->twig->render('@SetonoSyliusCMSPlugin/block/debug_message.twig', [
            'code' => $code,
        ]);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
