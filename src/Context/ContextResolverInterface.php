<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Symfony\Component\HttpFoundation\Request;

/**
 * Finds the object of one context key on the page being shown, for a slot whose template event doesn't pass it, such as
 * the product on a product page. #[AsContextResolver(key)] registers one, and a project's replaces a shipped one.
 * See "Context keys and resolvers" in docs/spec/extending.md
 */
interface ContextResolverInterface
{
    /**
     * The object for this key on the page being shown, or null on pages that don't have one
     *
     * @param Request $request always the main request, also when the slot renders inside a sub-request, such as Sylius's
     *                         taxon header
     */
    public function resolve(Request $request): ?object;
}
