<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Setono\SyliusCMSPlugin\Checker\Eligibility\Page\EligibilityCheckerInterface;
use Setono\SyliusCMSPlugin\Element\PageElement;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Renderer\RendererInterface;
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

    /** @var RendererInterface<ViewInterface> */
    private RendererInterface $viewRenderer;

    private EligibilityCheckerInterface $eligibilityChecker;

    /**
     * @param RendererInterface<ViewInterface> $viewRenderer
     */
    public function __construct(
        LocaleContextInterface $localeContext,
        PageRepositoryInterface $pageRepository,
        Environment $twig,
        RendererInterface $viewRenderer,
        EligibilityCheckerInterface $eligibilityChecker
    ) {
        $this->localeContext = $localeContext;
        $this->pageRepository = $pageRepository;
        $this->twig = $twig;
        $this->viewRenderer = $viewRenderer;
        $this->eligibilityChecker = $eligibilityChecker;
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $page = $this->pageRepository->findOneBySlug($this->localeContext->getLocaleCode(), $slug);
        if (null === $page || !$this->eligibilityChecker->isEligible($page)) {
            throw new NotFoundHttpException(sprintf('The page "%s" does not exist', $slug));
        }

        $content = '';

        $view = $page->getView();
        if (null !== $view) {
            $content = (string) $this->viewRenderer->render($view);
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/page.html.twig', [
            'page' => new PageElement($page, $content),
        ]));
    }
}
