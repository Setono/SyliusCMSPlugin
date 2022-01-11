<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Element\ViewElement;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Twig\Environment;

final class ViewRenderer implements ViewRendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private ViewRepositoryInterface $viewRepository;

    private Environment $twig;

    private RegistryInterface $templateRegistry;

    private BlockRendererInterface $blockRenderer;

    private ElementStackInterface $elementStack;

    private bool $debug;

    public function __construct(
        ViewRepositoryInterface $viewRepository,
        Environment $twig,
        RegistryInterface $templateRegistry,
        BlockRendererInterface $blockRenderer,
        ElementStackInterface $elementStack,
        bool $debug = false
    ) {
        $this->logger = new NullLogger();
        $this->viewRepository = $viewRepository;
        $this->twig = $twig;
        $this->templateRegistry = $templateRegistry;
        $this->blockRenderer = $blockRenderer;
        $this->elementStack = $elementStack;
        $this->debug = $debug;
    }

    public function render($view): string
    {
        if (is_string($view)) {
            $code = $view;
            $view = $this->viewRepository->findOneByCode($code);
            if (null === $view) {
                $this->logger->error(sprintf('The view "%s" is not defined', $code));

                return $this->renderNonExistingView($code);
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

        $this->elementStack->push($view);

        return $this->twig->render('@SetonoSyliusCMSPlugin/view.html.twig', [
            'view' => new ViewElement($view, $this->twig->render($template->getCode(), $context)),
        ]);
    }

    private function renderNonExistingView(string $code): string
    {
        if (!$this->debug) {
            return '';
        }

        return $this->twig->render('@SetonoSyliusCMSPlugin/view/debug_message.twig', [
            'code' => $code,
        ]);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
