<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\EntryContextAwareInterface;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * A project's entry source that fits only a shop as the host page's entry, listing the shops near it
 */
#[AsSource(code: 'nearby_shops', label: 'app.source.nearby_shops', of: EntryInterface::class, needs: ['entry'])]
final class NearbyShops implements SourceInterface, EntryContextAwareInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }

    public function supportsEntryContentType(string $code): bool
    {
        return 'shop' === $code;
    }
}
