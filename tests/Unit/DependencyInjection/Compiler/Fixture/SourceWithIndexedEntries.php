<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Sylius\Component\Core\Model\ProductInterface;

#[AsSource(code: 'related', label: 'app.related', of: ProductInterface::class, params: WithIndexedEntries::class)]
final class SourceWithIndexedEntries
{
}
