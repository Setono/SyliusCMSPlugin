<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ShopRenderContextFactoryTest extends TestCase
{
    use ProphecyTrait;

    private const ENTRY = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<CustomerContextInterface> */
    private ObjectProphecy $customerContext;

    private ChannelInterface $channel;

    private \DateTimeImmutable $now;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($this->channel);
        $this->customerContext = $this->prophesize(CustomerContextInterface::class);
        $this->customerContext->getCustomer()->willReturn(null);
        $this->now = new \DateTimeImmutable('2026-11-14 10:00:00');
        $this->requestStack = new RequestStack();
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
    public function it_carries_the_preview_mode_in_the_builders_preview(): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'da_DK'))]));

        self::assertSame('builder', $this->factory()->create()->previewMode);
    }

    /**
     * @test
     */
    public function it_takes_the_previews_locale_in_the_builders_preview_whatever_the_request(): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'en_US'))]));

        self::assertSame('en_US', $this->factory()->create()->localeCode);
    }

    /**
     * Shown to and Date set the customer group value and the time, and nothing else. See "Shown to and Date"
     * in docs/spec/targeting.md
     *
     * @test
     */
    public function it_shows_the_page_to_the_previews_customer_group_value_at_its_date_whoever_signed_in(): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn(null);
        $this->customerContext->getCustomer()->willReturn($customer->reveal());
        $date = new \DateTimeImmutable('2026-12-24T18:00:00Z');
        $this->previewing(new Preview(self::ENTRY, 'WEB', 'en_US', shownTo: 'wholesale', date: $date));

        $context = $this->factory()->create();

        self::assertSame('wholesale', $context->customerGroup);
        self::assertEquals($date, $context->now);
    }

    /**
     * A customer binding in preview shows its fallback. See "Bindings" in docs/spec/builder.md
     *
     * @test
     */
    public function it_has_no_customer_in_a_preview_whoever_signed_in(): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn(null);
        $this->customerContext->getCustomer()->willReturn($customer->reveal());
        $this->previewing(new Preview(self::ENTRY, 'WEB', 'en_US'));

        self::assertNull($this->factory()->create()->customer);
    }

    /**
     * @test
     */
    public function it_shows_the_page_to_guests_in_a_preview_that_says_so_while_a_customer_is_signed_in(): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn(null);
        $this->customerContext->getCustomer()->willReturn($customer->reveal());
        $this->previewing(new Preview(self::ENTRY, 'WEB', 'en_US', shownTo: '@guest'));

        $context = $this->factory()->create();

        self::assertSame('@guest', $context->customerGroup);
        self::assertSame($this->now, $context->now, 'A preview without a date shows the page now');
    }

    /**
     * A revision's read-only preview carries neither
     *
     * @test
     */
    public function it_shows_a_preview_without_shown_to_to_the_shopper_now(): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $customer->getGroup()->willReturn(null);
        $this->customerContext->getCustomer()->willReturn($customer->reveal());
        $this->previewing(new Preview(self::ENTRY, 'WEB', 'en_US'));

        $context = $this->factory()->create();

        self::assertSame('@no_group', $context->customerGroup);
        self::assertSame($this->now, $context->now);
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

    private function previewing(Preview $preview): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign($preview)]));
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
            new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock())),
        );
    }
}
