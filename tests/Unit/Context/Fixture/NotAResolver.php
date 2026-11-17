<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;

/**
 * A resolver that doesn't implement ContextResolverInterface
 */
#[AsContextResolver('store_location')]
final class NotAResolver
{
}
