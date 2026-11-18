<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

/**
 * The slots the plugin ships for positions in the stock Sylius 1.14 shop, which SetonoSyliusCMSExtension prepends to
 * setono_sylius_cms.slots. None is capped, and a project caps one with slots.<identifier>.cap. Each is labelled
 * setono_sylius_cms.slot.<identifier>. See "Stock slots" in docs/spec/slots.md
 *
 * @internal
 */
final class StockSlots
{
    /**
     * Identifier => [template event, priority of its block, the context keys it provides]
     *
     * @var array<string, array{string, int, list<string>}>
     */
    private const SLOTS = [
        'shop.top' => ['sylius.shop.layout.header', 30, []],
        'shop.before_content' => ['sylius.shop.layout.before_content', 0, []],
        'shop.after_content' => ['sylius.shop.layout.after_content', 0, []],
        'shop.footer' => ['sylius.shop.layout.footer', -10, []],
        'store_homepage' => ['sylius.shop.homepage', 100, []],
        // A layout event that fires on every page, so it renders only where the product resolver finds a product
        'product.top' => ['sylius.shop.layout.before_content', -10, ['product']],
        'product.sidebar' => ['sylius.shop.product.show.right_sidebar', 1, ['product']],
        'product.after_price' => ['sylius.shop.product.show.right_sidebar', 28, ['product']],
        'product.before_tabs' => ['sylius.shop.product.show.content', 22, ['product']],
        'product.details_tab' => ['sylius.shop.product.show.tab_details', 5, ['product']],
        'product.after_associations' => ['sylius.shop.product.show.content', 5, ['product']],
        // The taxon header and sidebar fire inside a Sylius sub-request
        'taxon.header' => ['sylius.shop.taxon.header', 1, ['taxon']],
        // The grid events don't carry the taxon, so it's resolved
        'taxon.before_grid' => ['sylius.shop.product.index.before_list', 0, ['taxon']],
        'taxon.after_grid' => ['sylius.shop.product.index.before_pagination', -10, ['taxon']],
        'taxon.sidebar' => ['sylius.shop.product.index.after_vertical_menu', 0, ['taxon']],
        'cart.after_summary' => ['sylius.shop.cart.suggestions', 100, []],
        'checkout.sidebar' => ['sylius.shop.checkout.sidebar', 0, []],
        'order.thank_you' => ['sylius.shop.order.thank_you.after_message', 0, []],
        'account.dashboard' => ['sylius.shop.account.dashboard.after_information', 0, []],
    ];

    private function __construct()
    {
    }

    /**
     * The stock slots as setono_sylius_cms.slots declares slots
     *
     * @return array<string, array{label: string, provides: list<string>, template_event: array{name: string, priority: int}}>
     */
    public static function config(): array
    {
        $slots = [];
        foreach (self::SLOTS as $identifier => [$event, $priority, $provides]) {
            $slots[$identifier] = [
                'label' => 'setono_sylius_cms.slot.' . $identifier,
                'provides' => $provides,
                'template_event' => ['name' => $event, 'priority' => $priority],
            ];
        }

        return $slots;
    }
}
