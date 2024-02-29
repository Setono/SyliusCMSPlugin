<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Setono\SyliusCMSPlugin\Checker\Eligibility\Page\EligibilityCheckerInterface;
use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class ShowPageAction
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly Environment $twig,
        private readonly EligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $page = $this->pageRepository->findOneBySlug(
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
            $slug,
        );

        // todo we need better preview handling. Right now if you preview a page that isn't enabled on the respective channel or hasn't got a translation for the respective locale the $page will be null. We should tell this to the user somehow

        if (null === $page || !$this->eligibilityChecker->isEligible($page)) {
            throw new NotFoundHttpException(sprintf('The page "%s" does not exist', $slug));
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/page.html.twig', [
            'page' => $page,
        ]));
    }
}
