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

    public function __construct(
        ViewRepositoryInterface $viewRepository,
        Environment $twig,
        RegistryInterface $templateRegistry
    ) {
        $this->viewRepository = $viewRepository;
        $this->twig = $twig;
        $this->templateRegistry = $templateRegistry;
    }

    public function render(string $view): string
    {
        $obj = $this->viewRepository->findOneByCode($view);
        if (null === $obj) {
            return '';
        }

        $templateKey = $obj->getTemplate();
        if (null === $templateKey) {
            return '';
        }

        if (!$this->templateRegistry->has($templateKey)) {
            return '';
        }

        $template = $this->templateRegistry->get($templateKey);

        return $this->twig->render($template->getPath());
    }
}
