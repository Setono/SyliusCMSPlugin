<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Binding\PropertyPathProperty;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Product;

/**
 * A property path declared in setono_sylius_cms.bindable_properties, and the shipped product.code, channel.name,
 * channel.contact_email, channel.contact_phone, customer.first_name, customer.last_name and customer.full_name. See
 * "Bindable properties" in docs/spec/extending.md
 */
final class PropertyPathPropertyTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_the_path_on_the_subject(): void
    {
        $product = new Product();
        $product->setCode('t_shirt');
        $channel = new Channel();
        $channel->setName('Web store');
        $channel->setContactEmail('shop@example.com');
        $channel->setContactPhoneNumber('+45 12 34 56 78');

        self::assertSame('t_shirt', (new PropertyPathProperty('code'))->resolve($product, new NoParams(), Rendering::context()));
        self::assertSame('Web store', (new PropertyPathProperty('name'))->resolve($channel, new NoParams(), Rendering::context()));
        self::assertSame('shop@example.com', (new PropertyPathProperty('contactEmail'))->resolve($channel, new NoParams(), Rendering::context()));
        self::assertSame('+45 12 34 56 78', (new PropertyPathProperty('contactPhoneNumber'))->resolve($channel, new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_reads_the_customer_s_names(): void
    {
        $customer = new Customer();
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');

        self::assertSame('Ada', (new PropertyPathProperty('firstName'))->resolve($customer, new NoParams(), Rendering::context()));
        self::assertSame('Lovelace', (new PropertyPathProperty('lastName'))->resolve($customer, new NoParams(), Rendering::context()));
        self::assertSame('Ada Lovelace', (new PropertyPathProperty('fullName'))->resolve($customer, new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_reads_a_path_through_another_record(): void
    {
        $product = new Product();
        $product->setCode('t_shirt');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName('T-shirt');

        self::assertSame('T-shirt', (new PropertyPathProperty('translation.name'))->resolve($product, new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_path_that_does_not_reach_a_value(): void
    {
        $product = new Product();

        self::assertNull((new PropertyPathProperty('mainTaxon.code'))->resolve($product, new NoParams(), Rendering::context()));
        self::assertNull((new PropertyPathProperty('brand.name'))->resolve($product, new NoParams(), Rendering::context()));
        self::assertNull((new PropertyPathProperty('code'))->resolve($product, new NoParams(), Rendering::context()));
    }

    /**
     * @test
     */
    public function it_is_empty_for_a_value_that_is_no_text_or_number(): void
    {
        self::assertNull((new PropertyPathProperty('variants'))->resolve(new Product(), new NoParams(), Rendering::context()));
    }
}
