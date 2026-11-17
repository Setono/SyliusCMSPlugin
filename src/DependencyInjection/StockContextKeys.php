<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * The context keys the plugin declares, which SetonoSyliusCMSExtension prepends to setono_sylius_cms.context_keys:
 * product and taxon, which the product and taxon page slots provide, and entry, which an entry the CMS renders provides
 * to its own tree and its layout's slots. Each is labelled setono_sylius_cms.context_key.<key>.
 * See "Context keys" in docs/spec/slots.md
 *
 * @internal
 */
final class StockContextKeys
{
    /** @var array<string, class-string> key => the class of the object it holds */
    private const KEYS = [
        'product' => ProductInterface::class,
        'taxon' => TaxonInterface::class,
        'entry' => EntryInterface::class,
    ];

    private function __construct()
    {
    }

    /**
     * The stock context keys as setono_sylius_cms.context_keys declares context keys
     *
     * @return array<string, array{class: class-string, label: string}>
     */
    public static function config(): array
    {
        $keys = [];
        foreach (self::KEYS as $key => $class) {
            $keys[$key] = ['class' => $class, 'label' => 'setono_sylius_cms.context_key.' . $key];
        }

        return $keys;
    }
}
