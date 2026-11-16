<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Revision;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUnavailableException;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUrlGenerator;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RevisionPreviewUrlGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    private PreviewTokenSigner $signer;

    /** @var array<string, mixed> the parameters of the URL generated last */
    private array $parameters = [];

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findOneByCode(Argument::any())->willReturn(null);
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'en_US', 'da_DK'));
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', 'en_US', 'de_DE', 'da_DK'));
        $this->signer = new PreviewTokenSigner('secret', new MockClock());
    }

    /**
     * @test
     */
    public function it_previews_the_revision_at_its_own_url_in_the_entrys_first_channel_and_that_channels_default_locale(): void
    {
        $revision = self::revision(['en_US' => 'about-us-then', 'da_DK' => 'om-os'], ['WEB', 'B2B']);

        $url = $this->generator()->generate($revision);

        self::assertSame('https://admin.example.com/preview', $url);
        self::assertSame('en_US', $this->parameters['_locale'] ?? null);
        self::assertSame('about-us-then', $this->parameters['path'] ?? null);
        $this->assertToken(new Preview($revision->getEntry()->getUuid(), 'B2B', 'en_US', 42));
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_channels_other_locales_in_code_order_when_the_revision_has_no_url_in_its_default_locale(): void
    {
        $revision = self::revision(['de_DE' => 'uber-uns', 'da_DK' => 'om-os'], ['B2B']);

        $this->generator()->generate($revision);

        self::assertSame('da_DK', $this->parameters['_locale'] ?? null);
        self::assertSame('om-os', $this->parameters['path'] ?? null);
    }

    /**
     * @test
     */
    public function it_tries_the_locales_by_code_in_a_channel_without_a_default_locale(): void
    {
        $channel = self::channel('B2B', 'en_US', 'de_DE', 'da_DK');
        $channel->setDefaultLocale(null);
        $this->channels->findOneByCode('B2B')->willReturn($channel);

        $this->generator()->generate(self::revision(['de_DE' => 'uber-uns', 'en_US' => 'about-us'], ['B2B']));

        self::assertSame('de_DE', $this->parameters['_locale'] ?? null);
    }

    /**
     * @test
     */
    public function it_previews_the_revision_in_the_channel_and_locale_asked_for(): void
    {
        $revision = self::revision(['en_US' => 'about-us', 'da_DK' => 'om-os'], ['WEB', 'B2B']);

        $this->generator()->generate($revision, 'WEB', 'da_DK');

        self::assertSame('da_DK', $this->parameters['_locale'] ?? null);
        self::assertSame('om-os', $this->parameters['path'] ?? null);
        $this->assertToken(new Preview($revision->getEntry()->getUuid(), 'WEB', 'da_DK', 42));
    }

    /**
     * @test
     *
     * @dataProvider provideUnavailablePreviews
     *
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     */
    public function it_refuses_a_preview_the_revision_has_no_page_for(array $slugs, array $channelCodes, ?string $channelCode, ?string $localeCode, string $message): void
    {
        $this->expectException(RevisionPreviewUnavailableException::class);
        $this->expectExceptionMessage($message);

        $this->generator()->generate(self::revision($slugs, $channelCodes), $channelCode, $localeCode);
    }

    /**
     * @return iterable<string, array{array<string, string>, list<string>, string|null, string|null, string}>
     */
    public static function provideUnavailablePreviews(): iterable
    {
        yield 'no channel' => [['en_US' => 'about-us'], [], null, null, 'The entry isn\'t in any channel'];
        yield 'another channel' => [['en_US' => 'about-us'], ['WEB'], 'B2B', null, 'The entry isn\'t in the channel B2B'];
        yield 'a channel the store does not have' => [['en_US' => 'about-us'], ['GONE'], null, null, 'There is no channel GONE'];
        yield 'a locale the channel does not have' => [['en_US' => 'about-us'], ['WEB'], 'WEB', 'de_DE', 'The channel WEB has no locale de_DE'];
        yield 'no url in the locale asked for' => [['en_US' => 'about-us'], ['WEB'], 'WEB', 'da_DK', 'The revision has no URL in da_DK'];
        yield 'no url in the channel' => [['de_DE' => 'uber-uns'], ['WEB'], null, null, 'The revision has no URL in the channel WEB'];
    }

    /**
     * @test
     */
    public function it_refuses_a_preview_of_a_content_type_without_urls_of_its_own(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        ChannelsAndAudience::modifier()->setChannels($recipe, ['WEB']);
        $revision = self::revisionOf($recipe);

        $this->expectException(RevisionPreviewUnavailableException::class);
        $this->expectExceptionMessage('The revision has no URL in the channel WEB');

        $this->generator()->generate($revision);
    }

    /**
     * @test
     */
    public function it_refuses_a_revision_that_was_never_stored(): void
    {
        $revision = self::revision(['en_US' => 'about-us'], ['WEB']);
        (new \ReflectionProperty(Revision::class, 'id'))->setValue($revision, null);

        $this->expectException(\InvalidArgumentException::class);

        $this->generator()->generate($revision);
    }

    private function assertToken(Preview $preview): void
    {
        $token = $this->parameters['_cms_preview'] ?? null;
        self::assertIsString($token);
        self::assertEquals($preview, $this->signer->verify($token));
    }

    private function generator(): RevisionPreviewUrlGenerator
    {
        $parameters = &$this->parameters;
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_cms_shop_entry', Argument::type('array'), UrlGeneratorInterface::ABSOLUTE_URL)->will(static function (array $arguments) use (&$parameters): string {
            /** @var array<string, mixed> $generated */
            $generated = $arguments[1];
            $parameters = $generated;

            return 'https://admin.example.com/preview';
        });

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->upgradeEntry(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));

        return new RevisionPreviewUrlGenerator(
            new RevisionReader($upgrader->reveal(), new EntryDenormalizer(self::contentTypes())),
            self::contentTypes(),
            $this->channels->reveal(),
            $this->signer,
            $urlGenerator->reveal(),
        );
    }

    /**
     * A revision of a page in these channels, whose content has these slugs, while the page's rows have other ones
     *
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     */
    private static function revision(array $slugs, array $channelCodes): Revision
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $page->setCurrentLocale($localeCode);
            $page->setTitle('About us');
            $page->setSlug($slug);
        }
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);

        $revision = self::revisionOf($page);

        $page->setCurrentLocale('en_US');
        $page->setSlug('about-us-now');

        return $revision;
    }

    private static function revisionOf(EntryInterface $entry): Revision
    {
        $revision = new Revision();
        $revision->setEntry($entry);
        $revision->setContent(CanonicalJson::encode((new EntryNormalizer(self::contentTypes()))->normalize($entry)));
        (new \ReflectionProperty(Revision::class, 'id'))->setValue($revision, 42);

        return $revision;
    }

    private static function channel(string $code, string $defaultLocaleCode, string ...$otherLocaleCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        foreach ([$defaultLocaleCode, ...$otherLocaleCodes] as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if ($localeCode === $defaultLocaleCode) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);
    }
}
