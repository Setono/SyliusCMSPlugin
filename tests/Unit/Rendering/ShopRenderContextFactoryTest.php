<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Rendering\ShopRenderContextFactory;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Customer\Model\CustomerInterface as BaseCustomerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class ShopRenderContextFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<CustomerContextInterface> */
    private ObjectProphecy $customerContext;

    private ChannelInterface $channel;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($this->channel);
        $this->customerContext = $this->prophesize(CustomerContextInterface::class);
        $this->customerContext->getCustomer()->willReturn(null);
        $this->now = new \DateTimeImmutable('2026-11-14 10:00:00');
    }

    /**
     * @test
     */
    public function it_creates_the_context_of_the_shop_request(): void
    {
        $entry = new Page();

        $context = $this->factory()->create(['entry' => $entry]);

        self::assertSame($this->channel, $context->channel);
        self::assertSame('da_DK', $context->localeCode);
        self::assertSame('DKK', $context->currencyCode);
        self::assertNull($context->customer);
        self::assertSame($this->now, $context->now);
        self::assertSame($entry, $context->get('entry'));
        self::assertSame(['entry' => $entry], $context->getHostPageContext());
        self::assertNull($context->previewMode);
    }

    /**
     * @test
     */
    public function a_shopper_who_is_not_signed_in_is_a_guest(): void
    {
        self::assertSame('@guest', $this->factory()->create()->customerGroup);
    }

    /**
     * @test
     */
    public function a_signed_in_customer_without_a_group_has_no_group(): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn(null);
        $this->customerContext->getCustomer()->willReturn($customer->reveal());

        $context = $this->factory()->create();

        self::assertSame($customer->reveal(), $context->customer);
        self::assertSame('@no_group', $context->customerGroup);
    }

    /**
     * @test
     */
    public function a_signed_in_customer_in_a_group_has_its_code(): void
    {
        $group = $this->prophesize(CustomerGroupInterface::class);
        $group->getCode()->willReturn('wholesale');
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn($group->reveal());
        $this->customerContext->getCustomer()->willReturn($customer->reveal());

        self::assertSame('wholesale', $this->factory()->create()->customerGroup);
    }

    /**
     * @test
     */
    public function a_customer_that_is_not_a_shop_customer_counts_as_a_guest(): void
    {
        $this->customerContext->getCustomer()->willReturn($this->prophesize(BaseCustomerInterface::class)->reveal());

        $context = $this->factory()->create();

        self::assertNull($context->customer);
        self::assertSame('@guest', $context->customerGroup);
    }

    /**
     * @test
     */
    public function it_needs_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());

        $this->expectException(\LogicException::class);

        $this->factory()->create();
    }

    private function factory(): ShopRenderContextFactory
    {
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('da_DK');

        $currencyContext = $this->prophesize(CurrencyContextInterface::class);
        $currencyContext->getCurrencyCode()->willReturn('DKK');

        $clock = $this->prophesize(ClockInterface::class);
        $clock->now()->willReturn($this->now);

        return new ShopRenderContextFactory(
            $this->channelContext->reveal(),
            $localeContext->reveal(),
            $currencyContext->reveal(),
            $this->customerContext->reveal(),
            $clock->reveal(),
        );
    }
}
