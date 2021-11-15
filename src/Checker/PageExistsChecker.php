<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker;

use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class PageExistsChecker implements PageExistsCheckerInterface
{
    private PageRepositoryInterface $pageRepository;

    public function __construct(PageRepositoryInterface $pageRepository)
    {
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

        // NOTICE: We cannot use the locale to check if the slug exists on the specific locale
        // because the locale isn't available at this point in time in the request cycle

        return $this->pageRepository->exists($slug);
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
