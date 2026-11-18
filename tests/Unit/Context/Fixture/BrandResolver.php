<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A resolver for a context key no config declares
 */
#[AsContextResolver('brand')]
final class BrandResolver implements ContextResolverInterface
{
    public function resolve(Request $request): ?object
    {
        return null;
    }
}
