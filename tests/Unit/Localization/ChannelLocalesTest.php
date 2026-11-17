<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Sylius\Component\Channel\Model\Channel as PlainChannel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;

final class ChannelLocalesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_a_channels_locales_its_default_first_and_then_the_others_by_code(): void
    {
        self::assertSame(['sv_SE', 'da_DK', 'en_US', 'nb_NO'], ChannelLocales::of(self::channel('NORDIC', 'sv_SE', 'nb_NO', 'en_US', 'sv_SE', 'da_DK')));
        self::assertSame(['da_DK', 'en_US'], ChannelLocales::of(self::channel('WEB', null, 'en_US', 'da_DK', null)));
        self::assertSame([], ChannelLocales::of(new PlainChannel()));
    }

    /**
     * @test
     */
    public function it_gives_the_locales_of_channels_each_once_in_the_order_the_store_lists_them_and_reads_the_channels_once_until_reset(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $repository */
        $repository = $this->prophesize(ChannelRepositoryInterface::class);
        $nameless = new Channel();
        $repository->findBy([], ['id' => 'ASC'])->willReturn([self::channel('WEB', 'en_US', 'en_US', 'da_DK'), self::channel('NORDIC', 'da_DK', 'sv_SE', 'da_DK'), $nameless])->shouldBeCalledTimes(2);
        $channelLocales = new ChannelLocales($repository->reveal());

        self::assertSame(['en_US', 'da_DK', 'sv_SE'], $channelLocales->ofChannels(['NORDIC', 'WEB', 'GONE']));
        self::assertSame(['da_DK', 'sv_SE'], $channelLocales->ofChannels(['NORDIC']));
        self::assertSame([], $channelLocales->ofChannels([]));

        $channelLocales->reset();
        self::assertSame(['en_US', 'da_DK'], $channelLocales->ofChannels(['WEB']));
    }

    /**
     * @test
     */
    public function it_gives_the_locales_of_every_channel_each_once(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $repository */
        $repository = $this->prophesize(ChannelRepositoryInterface::class);
        $repository->findBy([], ['id' => 'ASC'])->willReturn([self::channel('WEB', 'en_US', 'en_US', 'da_DK'), self::channel('NORDIC', 'sv_SE', 'da_DK', 'sv_SE'), new Channel()]);

        self::assertSame(['en_US', 'da_DK', 'sv_SE'], (new ChannelLocales($repository->reveal()))->all());
    }

    private static function channel(?string $code, ?string $default, ?string ...$localeCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        foreach ($localeCodes as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if (null !== $default && $default === $localeCode) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }
}
