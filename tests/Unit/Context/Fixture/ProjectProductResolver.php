<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A project's resolver for the product, which replaces the shipped one, such as for its own product page route
 */
#[AsContextResolver('product')]
final class ProjectProductResolver implements ContextResolverInterface
{
    public function resolve(Request $request): ?object
    {
        return null;
    }
}
