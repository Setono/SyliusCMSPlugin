<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\ChannelCompleteness;
use Setono\SyliusCMSPlugin\Admin\LocaleCompleteness;
use Setono\SyliusCMSPlugin\Admin\TranslationCompleteness;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;

/**
 * See "Overview" in docs/spec/admin.md
 */
final class TranslationCompletenessTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_counts_each_channel_in_the_stores_order_in_its_locales_its_default_locale_first(): void
    {
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['id' => 'ASC'])->willReturn([
            self::channel('WEB', 'Web store', 'en_US', 'da_DK', 'en_US'),
            // Named by its code without a name
            self::channel('NORDIC', null, 'sv_SE', 'da_DK', 'sv_SE'),
            // A channel without a code can't be counted
            self::channel(null, 'Unfinished', null),
        ]);
        $query = $this->prophesize(CompletenessQueryInterface::class);
        $query->count(['WEB' => ['en_US', 'da_DK'], 'NORDIC' => ['sv_SE', 'da_DK']])->willReturn([
            'WEB' => ['entries' => 3, 'complete' => ['en_US' => 3, 'da_DK' => 1]],
            'NORDIC' => ['entries' => 0, 'complete' => ['sv_SE' => 0, 'da_DK' => 0]],
        ]);

        $byChannel = (new TranslationCompleteness($channels->reveal(), $query->reveal()))->byChannel();

        self::assertEquals([
            new ChannelCompleteness('WEB', 'Web store', 3, [new LocaleCompleteness('en_US', 3, 3), new LocaleCompleteness('da_DK', 3, 1)]),
            new ChannelCompleteness('NORDIC', 'NORDIC', 0, [new LocaleCompleteness('sv_SE', 0, 0), new LocaleCompleteness('da_DK', 0, 0)]),
        ], $byChannel);
        self::assertSame(2, $byChannel[0]->locales[1]->incomplete());
        self::assertSame(0, $byChannel[0]->locales[0]->incomplete());
    }

    private static function channel(?string $code, ?string $name, ?string $defaultLocale, string ...$localeCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);
        foreach ($localeCodes as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if ($localeCode === $defaultLocale) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }
}
