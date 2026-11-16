<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryRow;
use Setono\SyliusCMSPlugin\Admin\TranslationChip;
use Setono\SyliusCMSPlugin\Admin\TranslationChips;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Channel\Model\Channel as BaseChannel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class TranslationChipsTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    /** @var ObjectProphecy<WorkingCopyProviderInterface> */
    private ObjectProphecy $workingCopies;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findBy([], ['id' => 'ASC'])->willReturn([
            self::channel('WEB', 'en_US', 'en_US', 'sv_SE', 'da_DK'),
            self::channel('CH', 'fr_CH', 'de_CH', 'fr_CH', 'da_DK'),
            self::channel('B2B', null, 'nb_NO'),
            new BaseChannel(),
        ]);
        $this->workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
    }

    /**
     * @test
     */
    public function it_gives_a_chip_per_locale_of_the_entry_s_channels_in_the_store_s_order_its_default_locale_first(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, ['CH', 'WEB']);
        $this->workingCopies->getWorkingCopyWithDraft($page, null)->willReturn($page);

        self::assertSame(
            ['en_US', 'da_DK', 'sv_SE', 'fr_CH', 'de_CH'],
            array_map(static fn (TranslationChip $chip): string => $chip->localeCode, $this->chips()->of(self::row($page))),
        );
    }

    /**
     * @test
     */
    public function it_says_the_working_copy_is_complete_or_counts_its_untranslated_and_outdated_values_or_has_no_translation(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $draft = new Draft();
        $workingCopy = self::page();
        $workingCopy->setBlockTree(new StoredTree(null, [
            new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Hello there', 'da_DK' => 'Hej']], translated: ['text' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]]]),
        ]));
        self::addTranslation($workingCopy, 'da_DK', 'Om os', true);
        self::addTranslation($workingCopy, 'sv_SE', '', true);
        $this->workingCopies->getWorkingCopyWithDraft($page, $draft)->willReturn($workingCopy);

        self::assertEquals([
            new TranslationChip('en_US', true, false, 0, 0),
            new TranslationChip('da_DK', true, false, 1, 1),
            new TranslationChip('sv_SE', false, false, 0, 0),
        ], $this->chips()->of(self::row($page, $draft)));
    }

    /**
     * @test
     */
    public function it_says_a_translation_is_held_back(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, ['B2B']);
        self::addTranslation($page, 'nb_NO', 'Om oss', false);
        $this->workingCopies->getWorkingCopyWithDraft($page, null)->willReturn($page);

        self::assertEquals([new TranslationChip('nb_NO', true, true, 1, 0)], $this->chips()->of(self::row($page)));
    }

    /**
     * @test
     */
    public function it_says_a_translation_without_a_title_is_no_translation(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, ['B2B']);
        $translation = new PageTranslation();
        $translation->setLocale('nb_NO');
        $translation->setSlug('om-oss');
        $page->addTranslation($translation);
        $this->workingCopies->getWorkingCopyWithDraft($page, null)->willReturn($page);

        self::assertEquals([new TranslationChip('nb_NO', false, false, 0, 0)], $this->chips()->of(self::row($page)));
    }

    /**
     * @test
     */
    public function it_puts_a_channel_s_default_locale_first_only_when_the_channel_has_it(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $channel = self::channel('WEB', null, 'sv_SE', 'da_DK');
        $elsewhere = new Locale();
        $elsewhere->setCode('fi_FI');
        $channel->setDefaultLocale($elsewhere);
        $this->channels->findBy([], ['id' => 'ASC'])->willReturn([$channel]);
        $this->workingCopies->getWorkingCopyWithDraft($page, null)->willReturn($page);

        self::assertSame(['da_DK', 'sv_SE'], array_map(static fn (TranslationChip $chip): string => $chip->localeCode, $this->chips()->of(self::row($page))));
    }

    /**
     * @test
     */
    public function it_gives_no_chips_to_an_entry_in_no_channel(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setChannels($page, []);
        $this->workingCopies->getWorkingCopyWithDraft($page, null)->willReturn($page);

        self::assertSame([], $this->chips()->of(self::row($page)));
    }

    /**
     * @test
     */
    public function a_chip_is_complete_when_the_locale_has_a_translation_with_nothing_untranslated_or_outdated(): void
    {
        self::assertTrue((new TranslationChip('en_US', true, false, 0, 0))->isComplete());
        self::assertTrue((new TranslationChip('en_US', true, true, 0, 0))->isComplete());
        self::assertFalse((new TranslationChip('en_US', false, false, 0, 0))->isComplete());
        self::assertFalse((new TranslationChip('en_US', true, false, 1, 0))->isComplete());
        self::assertFalse((new TranslationChip('en_US', true, false, 0, 1))->isComplete());
    }

    private function chips(): TranslationChips
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor(Argument::type('string'))->will(static fn (array $arguments): array => 'en_US' === $arguments[0] ? ['en_US'] : [$arguments[0], 'en_US']);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
        ]));

        return new TranslationChips(
            $this->workingCopies->reveal(),
            new TranslatableValues(
                new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
                new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
                new LayoutRegistry([], new FieldReader()),
                new FallbackValues($handlers, $fallbackChain->reveal()),
                $fallbackChain->reveal(),
            ),
            new ChannelLocales($this->channels->reveal()),
            new Availability(),
        );
    }

    private static function row(Page $page, ?Draft $draft = null): EntryRow
    {
        return new EntryRow($page, (new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]))->get('page'), null, null, [], 'never_published', $draft);
    }

    private static function page(): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');

        return $page;
    }

    private static function addTranslation(Page $page, string $localeCode, string $title, bool $shown): void
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setShown($shown);
        $page->addTranslation($translation);
    }

    private static function channel(string $code, ?string $defaultLocale, string ...$localeCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
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
