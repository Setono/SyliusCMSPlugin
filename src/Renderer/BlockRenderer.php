<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\DTO\Block\Block;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Twig\Environment;

final class BlockRenderer implements BlockRendererInterface
{
    private BlockRepositoryInterface $blockRepository;

    private Environment $twig;

    private bool $debug;

    public function __construct(BlockRepositoryInterface $blockRepository, Environment $twig, bool $debug = false)
    {
        $this->blockRepository = $blockRepository;
        $this->twig = $twig;
        $this->debug = $debug;
    }

    public function render($block): string
    {
        if (is_string($block)) {
            $code = $block;
            $block = $this->blockRepository->findOneByCode($code);
            if (null === $block) {
                return $this->renderNonExistingBlock($code);
            }
        }

        return $this->twig->render('@SetonoSyliusCMSPlugin/block.html.twig', [
            'block' => Block::createFromEntity($block),
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
}
