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

    public function __construct(BlockRepositoryInterface $blockRepository, Environment $twig)
    {
        $this->blockRepository = $blockRepository;
        $this->twig = $twig;
    }

    public function render(string $block): string
    {
        $obj = $this->blockRepository->findOneByCode($block);
        if (null === $obj) {
            return '';
        }

        return $this->twig->render('@SetonoSyliusCMSPlugin/block/block.html.twig', [
            'block' => Block::createFromEntity($obj),
        ]);
    }
}
