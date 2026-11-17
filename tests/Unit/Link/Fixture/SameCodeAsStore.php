<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

#[AsLinkTarget(code: 'app_store', label: 'app.link_target.other_store')]
final class SameCodeAsStore implements LinkTargetInterface
{
    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        return null;
    }

    public function search(string $query, string $localeCode): iterable
    {
        return [];
    }
}
