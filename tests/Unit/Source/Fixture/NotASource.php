<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A source that doesn't implement SourceInterface
 */
#[AsSource(code: 'not_a_source', label: 'app.source.not_a_source', of: ProductInterface::class)]
final class NotASource
{
}
