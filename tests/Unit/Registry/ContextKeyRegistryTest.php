<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyMetadata;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownContextKeyException;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Translation\TranslatableMessage;

final class ContextKeyRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_knows_the_context_keys_the_config_declares_with_their_class_and_label(): void
    {
        $registry = self::registry();

        self::assertTrue($registry->has('product'));
        self::assertFalse($registry->has('brand'));
        self::assertEquals(new ContextKeyMetadata('product', ProductInterface::class, 'setono_sylius_cms.context_key.product'), $registry->get('product'));
    }

    /**
     * Such as in a message about a key a snippet still declares after a deploy removed it
     *
     * @test
     */
    public function it_names_a_context_key_by_its_label_or_by_itself_when_the_code_no_longer_declares_it(): void
    {
        self::assertEquals(new TranslatableMessage('setono_sylius_cms.context_key.product'), self::registry()->labelOf('product'));
        self::assertSame('brand', self::registry()->labelOf('brand'));
    }

    /**
     * @test
     */
    public function it_lists_every_context_key_in_the_order_the_config_declares_them(): void
    {
        self::assertSame(['product', 'entry'], array_map(static fn (ContextKeyMetadata $key): string => $key->key, self::registry()->all()));
    }

    /**
     * @test
     */
    public function it_tells_whether_an_object_is_one_a_context_key_holds(): void
    {
        $product = new Product();

        self::assertTrue(self::registry()->get('product')->holds($product));
        self::assertFalse(self::registry()->get('entry')->holds($product));
    }

    /**
     * @test
     */
    public function it_throws_for_a_context_key_the_code_does_not_declare(): void
    {
        $this->expectException(UnknownContextKeyException::class);
        $this->expectExceptionMessage('There is no context key "brand". Declare it under setono_sylius_cms.context_keys');

        self::registry()->get('brand');
    }

    private static function registry(): ContextKeyRegistry
    {
        return new ContextKeyRegistry([
            'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
            'entry' => ['class' => EntryInterface::class, 'label' => 'setono_sylius_cms.context_key.entry'],
        ]);
    }
}
