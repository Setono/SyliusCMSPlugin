<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What pickers search: the things of one kind that match the query `q`, such as customer groups or products, each with
 * what a field stores to refer to it, its name in the admin user's locale and its thumbnail. With `refs`, a
 * comma-separated list of what fields store, it answers those that exist instead. A kind without a search answers 404.
 * See "JSON endpoints" in docs/spec/builder.md
 */
final readonly class SearchAction
{
    /**
     * @param ContainerInterface $searches the searches, by kind
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ContainerInterface $searches,
    ) {
    }

    public function __invoke(Request $request, string $kind): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->searches->has($kind)) {
            throw new NotFoundHttpException(sprintf('There is no search for "%s"', $kind));
        }

        $search = $this->searches->get($kind);
        if (!$search instanceof SearchInterface) {
            throw new \LogicException(sprintf('The search for "%s" must implement %s', $kind, SearchInterface::class));
        }

        $parameters = $request->query->all();

        // A picker names what a field holds by looking it up: refs=a,b
        $refs = $parameters['refs'] ?? null;
        if (is_string($refs)) {
            return new JsonResponse($search->find(array_values(array_filter(explode(',', $refs), static fn (string $ref): bool => '' !== $ref)), $request->getLocale()));
        }

        $query = $parameters['q'] ?? '';

        return new JsonResponse($search->search(is_string($query) ? $query : '', $request->getLocale()));
    }
}
