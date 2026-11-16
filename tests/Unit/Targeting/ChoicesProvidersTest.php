<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Targeting;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\LocaleChoicesProvider;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the channel, locale and customer group conditions pick from
 */
final class ChoicesProvidersTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function the_channels_are_the_stores_channels_with_a_code_by_name_read_once_until_reset(): void
    {
        $web = $this->prophesize(ChannelInterface::class);
        $web->getCode()->willReturn('WEB');
        $web->getName()->willReturn('Web store');
        $unnamed = $this->prophesize(ChannelInterface::class);
        $unnamed->getCode()->willReturn('B2B');
        $unnamed->getName()->willReturn(null);
        $uncoded = $this->prophesize(ChannelInterface::class);
        $uncoded->getCode()->willReturn(null);
        $uncoded->getName()->willReturn('Without a code');

        /** @var \Prophecy\Prophecy\ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $repository */
        $repository = $this->prophesize(ChannelRepositoryInterface::class);
        $repository->findBy([], ['name' => 'ASC'])->willReturn([$web->reveal(), $uncoded->reveal(), $unnamed->reveal()])->shouldBeCalledTimes(2);

        $provider = new ChannelChoicesProvider($repository->reveal());

        self::assertSame(['WEB' => 'Web store', 'B2B' => 'B2B'], $provider->getChoices('en_US'));
        self::assertSame(['WEB' => 'Web store', 'B2B' => 'B2B'], $provider->getChoices('da_DK'), 'A channel\'s name is the same in every locale');

        $provider->reset();
        $provider->getChoices('en_US');
    }

    /**
     * @test
     */
    public function the_locales_are_the_stores_locales_named_in_the_locale_asked_for_read_once_until_reset(): void
    {
        $danish = $this->prophesize(LocaleInterface::class);
        $danish->getCode()->willReturn('da_DK');
        $danish->getName('en_US')->willReturn('Danish (Denmark)');
        $danish->getName('da_DK')->willReturn('dansk (Danmark)');
        $unnamed = $this->prophesize(LocaleInterface::class);
        $unnamed->getCode()->willReturn('xx_XX');
        $unnamed->getName('en_US')->willReturn(null);
        $unnamed->getName('da_DK')->willReturn(null);
        $uncoded = $this->prophesize(LocaleInterface::class);
        $uncoded->getCode()->willReturn(null);
        $uncoded->getName('en_US')->willReturn('Without a code');
        $uncoded->getName('da_DK')->willReturn('Uden kode');

        /** @var \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<LocaleInterface>> $repository */
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findBy([], ['code' => 'ASC'])->willReturn([$uncoded->reveal(), $danish->reveal(), $unnamed->reveal()])->shouldBeCalledTimes(2);

        $provider = new LocaleChoicesProvider($repository->reveal());

        self::assertSame(['da_DK' => 'Danish (Denmark)', 'xx_XX' => 'xx_XX'], $provider->getChoices('en_US'));
        self::assertSame(['da_DK' => 'dansk (Danmark)', 'xx_XX' => 'xx_XX'], $provider->getChoices('da_DK'));

        $provider->reset();
        $provider->getChoices('en_US');
    }

    /**
     * @test
     */
    public function the_customer_groups_are_guests_and_customers_without_a_group_then_the_stores_groups_read_once_until_reset(): void
    {
        $wholesale = $this->prophesize(CustomerGroupInterface::class);
        $wholesale->getCode()->willReturn('wholesale');
        $wholesale->getName()->willReturn('Wholesale');
        $unnamed = $this->prophesize(CustomerGroupInterface::class);
        $unnamed->getCode()->willReturn('vip');
        $unnamed->getName()->willReturn(null);
        $uncoded = $this->prophesize(CustomerGroupInterface::class);
        $uncoded->getCode()->willReturn(null);
        $uncoded->getName()->willReturn('Without a code');

        /** @var \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $repository */
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findBy([], ['name' => 'ASC'])->willReturn([$wholesale->reveal(), $uncoded->reveal(), $unnamed->reveal()])->shouldBeCalledTimes(2);

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('setono_sylius_cms.ui.audience.guests', [], null, 'da_DK')->willReturn('Gæster');
        $translator->trans('setono_sylius_cms.ui.audience.no_group', [], null, 'da_DK')->willReturn('Logget ind uden gruppe');
        $translator->trans('setono_sylius_cms.ui.audience.guests', [], null, 'en_US')->willReturn('Guests');
        $translator->trans('setono_sylius_cms.ui.audience.no_group', [], null, 'en_US')->willReturn('Signed in without a group');

        $provider = new CustomerGroupChoicesProvider($repository->reveal(), $translator->reveal());

        self::assertSame(['@guest' => 'Gæster', '@no_group' => 'Logget ind uden gruppe', 'wholesale' => 'Wholesale', 'vip' => 'vip'], $provider->getChoices('da_DK'));
        self::assertSame(['@guest' => 'Guests', '@no_group' => 'Signed in without a group', 'wholesale' => 'Wholesale', 'vip' => 'vip'], $provider->getChoices('en_US'));

        $provider->reset();
        $provider->getChoices('en_US');
    }
}
