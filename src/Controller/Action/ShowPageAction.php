<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Setono\SyliusCMSPlugin\Renderer\ViewRendererInterface;
use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class ShowPageAction
{
    private LocaleContextInterface $localeContext;

    private PageRepositoryInterface $pageRepository;

    private Environment $twig;

    private ViewRendererInterface $viewRenderer;

    public function __construct(
        LocaleContextInterface $localeContext,
        PageRepositoryInterface $pageRepository,
        Environment $twig,
        ViewRendererInterface $viewRenderer
    ) {
        $this->localeContext = $localeContext;
        $this->pageRepository = $pageRepository;
        $this->twig = $twig;
        $this->viewRenderer = $viewRenderer;
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $page = $this->pageRepository->findOneBySlug($this->localeContext->getLocaleCode(), $slug);
        if (null === $page) {
            throw new NotFoundHttpException(sprintf('The page "%s" does not exist', $slug));
        }

        $content = '';

        $view = $page->getView();
        if (null !== $view) {
            $this->viewRenderer->render($view);
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/page.html.twig', [
            'content' => $content,
        ]));
    }
}
