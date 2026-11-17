<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A project's resolver for a context key of its own, the store location of its store locator's page
 */
#[AsContextResolver('store_location')]
final class StoreLocationResolver implements ContextResolverInterface
{
    public function resolve(Request $request): ?object
    {
        $location = $request->attributes->get('location');

        return is_object($location) ? $location : null;
    }
}
