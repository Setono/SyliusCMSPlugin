<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Collection\PageNumber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The page of an entry's paginated Entries block that a request to the entry's URL, or the homepage, asks for with
 * ?page=N. Page 1 is the URL without it, so ?page=1 redirects there, and a ?page that isn't a whole number of 1 or more
 * answers 404. Whether the page exists is checked before rendering. See "Rendering an entry at its own URL" in
 * docs/spec/rendering.md
 *
 * @internal
 */
final class RequestedPage
{
    private function __construct()
    {
    }

    /**
     * The page the request asks for, page 1 without ?page
     *
     * @return int<1, max>
     *
     * @throws NotFoundHttpException when ?page isn't a page
     */
    public static function of(Request $request): int
    {
        $query = $request->query->all();
        if (!array_key_exists(PageNumber::QUERY_PARAMETER, $query)) {
            return 1;
        }

        return PageNumber::parse($query[PageNumber::QUERY_PARAMETER])
            ?? throw new NotFoundHttpException(sprintf('"%s" isn\'t a page: a page is a whole number of 1 or more', is_string($query[PageNumber::QUERY_PARAMETER]) ? $query[PageNumber::QUERY_PARAMETER] : get_debug_type($query[PageNumber::QUERY_PARAMETER])));
    }

    /**
     * A permanent redirect from ?page=1 to the URL without it, keeping the rest of the query string, or null when the
     * request doesn't ask for ?page=1
     */
    public static function redirectFromPage1(Request $request): ?RedirectResponse
    {
        $query = $request->query->all();
        if ('1' !== ($query[PageNumber::QUERY_PARAMETER] ?? null)) {
            return null;
        }

        unset($query[PageNumber::QUERY_PARAMETER]);
        $url = $request->getBaseUrl() . $request->getPathInfo();
        $queryString = Request::normalizeQueryString(http_build_query($query, '', '&', \PHP_QUERY_RFC3986));

        return new RedirectResponse('' === $queryString ? $url : $url . '?' . $queryString, Response::HTTP_MOVED_PERMANENTLY);
    }
}
