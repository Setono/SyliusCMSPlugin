<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Twig\Environment;

final class ViewRenderer implements ViewRendererInterface
{
    private ViewRepositoryInterface $viewRepository;

    private Environment $twig;

    private RegistryInterface $templateRegistry;

    private BlockRendererInterface $blockRenderer;

    public function __construct(
        ViewRepositoryInterface $viewRepository,
        Environment $twig,
        RegistryInterface $templateRegistry,
        BlockRendererInterface $blockRenderer
    ) {
        $this->viewRepository = $viewRepository;
        $this->twig = $twig;
        $this->templateRegistry = $templateRegistry;
        $this->blockRenderer = $blockRenderer;
    }

    public function render($view): string
    {
        if (is_string($view)) {
            $view = $this->viewRepository->findOneByCode($view);
            if (null === $view) {
                return '';
            }
        }

        $templateCode = $view->getTemplate();
        if (null === $templateCode) {
            return '';
        }

        if (!$this->templateRegistry->has($templateCode)) {
            return '';
        }

        $template = $this->templateRegistry->get($templateCode);

        $context = [];
        foreach ($view->getViewBlocks() as $viewBlock) {
            $block = $viewBlock->getBlock();
            if (null === $block) {
                continue;
            }

            $key = sprintf('sscms_%s', (string) $viewBlock->getSection());
            $content = $this->blockRenderer->render($block);
            $context[$key] = isset($context[$key]) ? $context[$key] . $content : $content;
        }

        $renderedView = $this->twig->render($template->getCode(), $context);

        return $this->twig->render('@SetonoSyliusCMSPlugin/view.html.twig', [
            'view' => $view,
            'rendered_view' => $renderedView,
        ]);
    }
}
