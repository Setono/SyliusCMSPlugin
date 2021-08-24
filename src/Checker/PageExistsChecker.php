<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker;

use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;

final class PageExistsChecker implements PageExistsCheckerInterface
{
    private LocaleContextInterface $localeContext;

    private PageRepositoryInterface $pageRepository;

    public function __construct(LocaleContextInterface $localeContext, PageRepositoryInterface $pageRepository)
    {
        $this->localeContext = $localeContext;
        $this->pageRepository = $pageRepository;
    }

    /**
     * Returns true if a page exists given the URL
     */
    public function checkUrl(Request $request): bool
    {
        $url = $request->getPathInfo();

        $slug = self::getSlugFromUrl($url);
        if (null === $slug || '' === $slug) {
            return false;
        }

        return $this->pageRepository->exists($this->localeContext->getLocaleCode(), $slug);
    }

    /**
     * NOTICE that we presume that slugs cannot contain a slash (/)
     * todo add this to validation rules for PageTranslation entity
     */
    private static function getSlugFromUrl(string $url): ?string
    {
        $pos = strrpos($url, '/');
        if ($pos === false) { // note: three equal signs
            return null;
        }

        return substr($url, $pos + 1);
    }
}
