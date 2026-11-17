<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Link;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The test application's own link target, as a project adds one: its store locations, by code, which #[AsLinkTarget]
 * registers through autoconfiguration
 */
#[AsLinkTarget(code: 'app_store', label: 'app.cms.link_target.store')]
final class StoreLinkTarget implements LinkTargetInterface
{
    private const STORES = ['copenhagen' => 'Copenhagen', 'aarhus' => 'Aarhus'];

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $name = self::STORES[$ref] ?? null;

        return null === $name ? null : new ResolvedLink('app_store', $ref, sprintf('https://stores.example.com/%s', $ref), $name);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach (self::STORES as $ref => $name) {
            if (str_contains(strtolower($name), strtolower($query))) {
                yield ['ref' => $ref, 'name' => $name, 'thumbnail' => null];
            }
        }
    }
}
