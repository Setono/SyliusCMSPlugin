<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\NewEntryLocaleResolver;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Sylius\Component\Channel\Model\Channel as PlainChannel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

final class NewEntryLocaleResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findOneByCode(Argument::any())->willReturn(null);
    }

    /**
     * @test
     */
    public function it_is_the_admin_users_locale_when_one_of_the_entrys_channels_has_it(): void
    {
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', 'en_US', 'de_DE'));
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'en_US', 'en_US', 'da_DK'));

        self::assertSame('da_DK', $this->resolver('da_DK')->resolve(self::entryIn('B2B', 'WEB')));
    }

    /**
     * @test
     */
    public function it_is_the_default_locale_of_the_entrys_first_channel_that_has_one_otherwise(): void
    {
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', null, 'de_DE'));
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'fr_FR', 'fr_FR', 'en_US'));
        $this->channels->findOneByCode('XYZ')->willReturn(self::channel('XYZ', 'nl_NL', 'nl_NL'));

        self::assertSame('fr_FR', $this->resolver('da_DK')->resolve(self::entryIn('B2B', 'WEB', 'XYZ')));
    }

    /**
     * @test
     */
    public function it_is_the_stores_default_locale_for_an_entry_in_no_channel_with_a_locale(): void
    {
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', null));
        $this->channels->findOneByCode('POS')->willReturn(self::plainChannel('POS'));

        self::assertSame('en_GB', $this->resolver('da_DK')->resolve(self::entryIn('B2B', 'GONE', 'POS')));
        self::assertSame('en_GB', $this->resolver('da_DK')->resolve(new Page()));
    }

    private function resolver(string $adminLocale): NewEntryLocaleResolver
    {
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn($adminLocale);
        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_GB');

        return new NewEntryLocaleResolver($localeContext->reveal(), $this->channels->reveal(), $translationLocaleProvider->reveal());
    }

    private static function entryIn(string ...$channelCodes): Page
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);

        return $page;
    }

    private static function channel(string $code, ?string $defaultLocale, string ...$locales): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        foreach ($locales as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if ($localeCode === $defaultLocale) {
                $channel->setDefaultLocale($locale);
            }
        }

        if (null !== $defaultLocale && null === $channel->getDefaultLocale()) {
            $locale = new Locale();
            $locale->setCode($defaultLocale);
            $channel->setDefaultLocale($locale);
        }

        return $channel;
    }

    /**
     * A channel without locales, as a store without Sylius's core channel model has
     */
    private static function plainChannel(string $code): PlainChannel
    {
        $channel = new PlainChannel();
        $channel->setCode($code);

        return $channel;
    }
}
