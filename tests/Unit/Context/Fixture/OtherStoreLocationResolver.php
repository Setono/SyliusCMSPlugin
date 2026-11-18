<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A second resolver for the store location
 */
#[AsContextResolver('store_location')]
final class OtherStoreLocationResolver implements ContextResolverInterface
{
    public function resolve(Request $request): ?object
    {
        return null;
    }
}
