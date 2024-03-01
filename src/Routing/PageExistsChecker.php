<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class PageExistsChecker implements PageExistsCheckerInterface
{
    public function __construct(private readonly PageRepositoryInterface $pageRepository)
    {
    }

    public function checkUrl(Request $request): bool
    {
        $path = $request->getPathInfo();

        foreach (self::getSlugsFromUrl($path) as $slug) {
            // NOTICE: We cannot use the locale to check if the slug exists on the specific locale
            // because the locale isn't available at this point in time in the request cycle
            if ($this->pageRepository->exists($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Generator<string>
     */
    private static function getSlugsFromUrl(string $path): \Generator
    {
        $parts = explode('/', trim($path, '/'));

        do {
            yield implode('/', $parts);

            array_shift($parts);
        } while ([] !== $parts);
    }
}
