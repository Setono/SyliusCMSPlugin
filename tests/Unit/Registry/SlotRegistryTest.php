<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownSlotException;

final class SlotRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_knows_the_slots_the_config_declares_by_identifier(): void
    {
        $registry = self::registry();

        self::assertTrue($registry->has('shop.footer'));
        self::assertFalse($registry->has('shop.gone'));
        self::assertEquals(new SlotMetadata('product.after_price', 'setono_sylius_cms.slot.product.after_price', ['product'], 1, 'sylius.shop.product.show.right_sidebar', 28), $registry->get('product.after_price'));
        self::assertEquals(new SlotMetadata('app.store_locator.top', 'app.cms.slot.store_locator_top', ['store_location']), $registry->get('app.store_locator.top'));
    }

    /**
     * @test
     */
    public function it_lists_every_slot_in_the_order_the_config_declares_them(): void
    {
        self::assertSame(['shop.footer', 'product.after_price', 'app.store_locator.top'], self::identifiers(self::registry()->all()));
    }

    /**
     * @test
     */
    public function it_offers_the_slots_that_provide_at_least_the_context_keys_a_snippet_needs(): void
    {
        $registry = self::registry();

        self::assertSame(['shop.footer', 'product.after_price', 'app.store_locator.top'], self::identifiers($registry->providing([])));
        self::assertSame(['product.after_price'], self::identifiers($registry->providing(['product'])));
        self::assertSame([], self::identifiers($registry->providing(['product', 'store_location'])));
    }

    /**
     * @test
     */
    public function it_throws_for_a_slot_the_code_does_not_declare(): void
    {
        $this->expectException(UnknownSlotException::class);
        $this->expectExceptionMessage('There is no slot "shop.gone". Declare it under setono_sylius_cms.slots');

        self::registry()->get('shop.gone');
    }

    /**
     * @param list<SlotMetadata> $slots
     *
     * @return list<string>
     */
    private static function identifiers(array $slots): array
    {
        return array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $slots);
    }

    private static function registry(): SlotRegistry
    {
        return new SlotRegistry([
            'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.layout.footer', 'priority' => -10]],
            'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => 1, 'template_event' => ['name' => 'sylius.shop.product.show.right_sidebar', 'priority' => 28]],
            'app.store_locator.top' => ['label' => 'app.cms.slot.store_locator_top', 'provides' => ['store_location'], 'cap' => null],
        ]);
    }
}
