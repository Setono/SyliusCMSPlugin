<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\ChannelHomepages;
use Setono\SyliusCMSPlugin\Admin\EntryRevisions;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\EntryShareLinks;
use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Admin\TranslationChips;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Twig\AdminExtension;
use Setono\SyliusCMSPlugin\Twig\AdminRuntime;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in AdminFixture, one directory per function. Their --DATA-- sections build the template
 * variables with the public helpers below
 */
final class AdminExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    /** @var list<Homepage> the store's homepage rows */
    private array $homepages = [];

    /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> */
    private ObjectProphecy $customerGroups;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $customerGroups */
        $customerGroups = $this->prophesize(RepositoryInterface::class);
        $this->customerGroups = $customerGroups;
        // A group without a name is named by its code, and one without a code isn't named
        $this->customerGroups->findAll()->willReturn([
            self::customerGroup('wholesale', 'Wholesale'),
            self::customerGroup('retail', null),
            self::customerGroup(null, 'Unfinished'),
        ]);
    }

    /**
     * @test
     */
    public function its_runtime_reads_the_customer_groups_once_until_it_is_reset(): void
    {
        $this->customerGroups->findAll()->willReturn([self::customerGroup('wholesale', 'Wholesale')])->shouldBeCalledTimes(2);
        $runtime = $this->runtime();

        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
        $runtime->reset();
        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
    }

    /**
     * @return array{page: Page}
     */
    public function aPage(string $title): array
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setSlug('about-us');

        return ['page' => $page];
    }

    /**
     * @return array{page: Page}
     */
    public function aPageInTheWebChannel(string $title): array
    {
        $page = $this->aPage($title)['page'];
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);

        return ['page' => $page];
    }

    /**
     * The homepage of the store's channel WEB
     *
     * @return array{page: Page}
     */
    public function aHomepage(string $title): array
    {
        $page = $this->aPage($title)['page'];
        Homepages::modifier()->setHomepageOf($page, ['WEB']);
        $this->homepages = $page->getHomepages()->getValues();

        return ['page' => $page];
    }

    /**
     * @return array{page: Page}
     */
    public function aPageWithABlockOfATypeTheCodeNoLongerHas(string $key): array
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, [new StoredBlock($key, 'promo', 1)]));

        return ['page' => $page];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/AdminFixture';
    }

    /**
     * Twig before 3.13, such as the lowest 3.7 the plugin allows, reads the fixtures from here
     */
    protected function getFixturesDir(): string
    {
        return self::getFixturesDirectory();
    }

    protected function getExtensions(): array
    {
        return [new AdminExtension()];
    }

    /**
     * @return list<FactoryRuntimeLoader>
     */
    protected function getRuntimeLoaders(): array
    {
        $drafts = $this->prophesize(DraftRepositoryInterface::class);
        $drafts->findByEntries(Argument::any())->willReturn([]);
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => 'en_US', 'path' => 'about-us'])->willReturn('/en_US/about-us');
        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_US');
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $scheduledPublishes->findByEntries(Argument::any())->willReturn([]);
        $problemCounts = $this->prophesize(ProblemCountProviderInterface::class);
        $problemCounts->getCounts(Argument::any())->willReturn([]);

        $entryRows = new EntryRows(
            $drafts->reveal(),
            self::contentTypes(),
            new StatusLabeler(new MockClock()),
            $urlGenerator->reveal(),
            $translationLocaleProvider->reveal(),
            $localeContext->reveal(),
            $scheduledPublishes->reveal(),
            new MockClock(),
            $problemCounts->reveal(),
        );
        $oldSlugs = $this->prophesize(OldSlugRepositoryInterface::class);
        $oldSlugs->findByEntry(Argument::any())->willReturn([]);
        $entryUrls = new EntryUrls($drafts->reveal(), self::contentTypes(), $urlGenerator->reveal(), $oldSlugs->reveal());
        $revisions = $this->prophesize(RevisionRepositoryInterface::class);
        $revisions->findByEntry(Argument::type(Page::class))->willReturn([
            self::revision('2026-11-14 10:00:00', 'Jane Roe'),
            self::revision('2026-11-01 10:00:00', 'John Doe'),
        ]);
        $entryRevisions = new EntryRevisions($revisions->reveal());
        $entryShareLinks = $this->entryShareLinks();
        $runtime = $this->runtime();
        $translationChips = $this->translationChips();
        $whereItShows = $this->whereItShows();
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::any())->willReturnArgument(0);
        $problemFinder = new ProblemFinder([new UnknownBlockType(new BlockTypeRegistry([], new FieldReader()))], $workingCopies->reveal());
        $homepages = $this->prophesize(HomepageRepositoryInterface::class);
        // Prophecy binds a closure to the double, so a static one reads the rows a --DATA-- section sets through a reference
        $rows = &$this->homepages;
        $homepages->findByChannelCodes(Argument::any())->will(static function () use (&$rows): array {
            return $rows;
        });
        $web = new Channel();
        $web->setCode('WEB');
        $web->setName('Web store');
        $b2b = new Channel();
        $b2b->setCode('B2B');
        $b2b->setName('Wholesale');
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['id' => 'ASC'])->willReturn([$web, $b2b]);
        $channelHomepages = new ChannelHomepages($channels->reveal(), $homepages->reveal());

        return [new FactoryRuntimeLoader([
            ChannelHomepages::class => static fn (): ChannelHomepages => $channelHomepages,
            EntryRows::class => static fn (): EntryRows => $entryRows,
            EntryUrls::class => static fn (): EntryUrls => $entryUrls,
            TranslationChips::class => static fn (): TranslationChips => $translationChips,
            WhereItShows::class => static fn (): WhereItShows => $whereItShows,
            EntryRevisions::class => static fn (): EntryRevisions => $entryRevisions,
            EntryShareLinks::class => static fn (): EntryShareLinks => $entryShareLinks,
            AdminRuntime::class => static fn (): AdminRuntime => $runtime,
            ProblemFinder::class => static fn (): ProblemFinder => $problemFinder,
        ])];
    }

    private function translationChips(): TranslationChips
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopyWithDraft(Argument::any(), null)->willReturnArgument(0);
        $english = new Locale();
        $english->setCode('en_US');
        $danish = new Locale();
        $danish->setCode('da_DK');
        $web = new Channel();
        $web->setCode('WEB');
        $web->addLocale($english);
        $web->addLocale($danish);
        $web->setDefaultLocale($english);
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['id' => 'ASC'])->willReturn([$web]);
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor(Argument::type('string'))->will(static fn (array $arguments): array => [$arguments[0]]);

        return new TranslationChips(
            $workingCopies->reveal(),
            new TranslatableValues(
                self::contentTypes(),
                new BlockTypeRegistry([], new FieldReader()),
                new LayoutRegistry([], new FieldReader()),
                new FallbackValues(new FieldHandlerLocator(new ServiceLocator([])), $fallbackChain->reveal()),
                $fallbackChain->reveal(),
            ),
            $channels->reveal(),
        );
    }

    /**
     * The store has one channel, WEB, in en_US and da_DK, where no route takes an entry's URL
     */
    private function whereItShows(): WhereItShows
    {
        $english = new Locale();
        $english->setCode('en_US');
        $danish = new Locale();
        $danish->setCode('da_DK');
        $web = new Channel();
        $web->setCode('WEB');
        $web->addLocale($danish);
        $web->addLocale($english);
        $web->setDefaultLocale($english);
        /** @var ObjectProphecy<ChannelRepositoryInterface<CoreChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['id' => 'ASC'])->willReturn([$web]);
        $takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $takenUrls->find(Argument::cetera())->willReturn([]);

        return new WhereItShows($channels->reveal(), new Availability(), $takenUrls->reveal(), new MockClock());
    }

    /**
     * The store has one channel, WEB, named Web store on shop.example.com, and every page has one share link there in
     * en_US, whose token is abc
     */
    private function entryShareLinks(): EntryShareLinks
    {
        $english = new Locale();
        $english->setCode('en_US');
        $web = new Channel();
        $web->setCode('WEB');
        $web->setName('Web store');
        $web->setHostname('shop.example.com');
        $web->addLocale($english);
        $web->setDefaultLocale($english);
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findOneByCode('WEB')->willReturn($web);
        $shareLinks = $this->prophesize(ShareLinkRepositoryInterface::class);
        $shareLinks->findByEntry(Argument::type(Page::class))->will(static function (array $arguments): array {
            $page = $arguments[0];
            self::assertInstanceOf(Page::class, $page);
            $shareLink = new ShareLink();
            $shareLink->setEntry($page);
            $shareLink->setChannelCode('WEB');
            $shareLink->setLocaleCode('en_US');
            $shareLink->setToken('abc');

            return [$shareLink];
        });
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::any())->willReturnArgument(0);
        $routes = new RouteCollection();
        $routes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.+']));

        return new EntryShareLinks(
            $shareLinks->reveal(),
            new ShareLinkUrlGenerator($workingCopies->reveal(), self::contentTypes(), $channels->reveal(), new UrlGenerator($routes, new RequestContext(host: 'admin.example.com', scheme: 'https'))),
            $channels->reveal(),
            $workingCopies->reveal(),
            self::contentTypes(),
        );
    }

    private function runtime(): AdminRuntime
    {
        return new AdminRuntime(self::contentTypes(), $this->customerGroups->reveal());
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
        ]);
    }

    private static function revision(string $publishedAt, string $publishedBy): Revision
    {
        $revision = new Revision();
        $revision->setPublishedAt(new \DateTimeImmutable($publishedAt, new \DateTimeZone('UTC')));
        $revision->setPublishedBy($publishedBy);

        return $revision;
    }

    private static function customerGroup(?string $code, ?string $name): CustomerGroupInterface
    {
        $group = new CustomerGroup();
        $group->setCode($code);
        $group->setName($name);

        return $group;
    }
}
