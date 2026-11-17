<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * A source that lists a class or interface that doesn't exist
 */
// @phpstan-ignore argument.type (the class doesn't exist, on purpose)
#[AsSource(code: 'unknown_of', label: 'app.source.unknown_of', of: 'App\Entity\Recipe')]
final class UnknownOf implements SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }
}
