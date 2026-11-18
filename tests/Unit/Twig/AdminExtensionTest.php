<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\ChannelHomepages;
use Setono\SyliusCMSPlugin\Admin\ComingUp;
use Setono\SyliusCMSPlugin\Admin\EntryPlacements;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\EntryShareLinks;
use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Admin\NeedsAttention;
use Setono\SyliusCMSPlugin\Admin\PageScopeSummaries;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Admin\TranslationChips;
use Setono\SyliusCMSPlugin\Admin\TranslationCompleteness;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Builder\RegistryExporter;
use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Setono\SyliusCMSPlugin\Context\DeclaredContextInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Homepage\CMSHomepageChannels;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaLimits;
use Setono\SyliusCMSPlugin\Media\MediaLocales;
use Setono\SyliusCMSPlugin\Media\MediaTypes;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Support\SnippetPreviews;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\OnProductPage;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\SnippetUses;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Twig\AdminExtension;
use Setono\SyliusCMSPlugin\Twig\AdminRuntime;
use Setono\SyliusCMSPlugin\Twig\EntryOverviewRuntime;
use Setono\SyliusCMSPlugin\Twig\OverviewRuntime;
use Setono\SyliusCMSPlugin\Twig\PageScopeEditorRuntime;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
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
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in AdminFixture, one directory per function. Their --DATA-- sections build the template
 * variables with the public helpers below
 */
final class AdminExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    /** @var list<HomepageInterface> the store's homepage rows */
    private array $homepages = [];

    /** @var list<Placement> the store's placements */
    private array $placements = [];

    /** @var list<EntryInterface> the entries whose trees may show a snippet */
    private array $showingSnippets = [];

    /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> */
    private ObjectProphecy $customerGroups;

    /** @var list<EntryInterface> the entries that need attention, the one edited last first */
    private array $needingAttention = [];

    /** @var list<EntryInterface> the entries with a draft, the one edited last first */
    private array $withDraft = [];

    /** @var list<EntryInterface> the entries with a date window */
    private array $dated = [];

    /** How many images miss alt text */
    private int $missingAltText = 0;

    /** @var array<string, array{entries: int, complete: array<string, int>}> the completeness query's counts, by channel */
    private array $completeness = [];

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
    public function it_reads_the_customer_groups_in_its_runtime_once_until_it_is_reset(): void
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
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, ['WEB']);
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

    /**
     * A snippet placed in the footer
     *
     * @return array{snippet: Snippet}
     */
    public function aSnippetPlacedInTheFooter(): array
    {
        $snippet = new Snippet();
        $this->placements = [new Placement($snippet, 'shop.footer', 0)];

        return ['snippet' => $snippet];
    }

    /**
     * A snippet that a published page with the title shows
     *
     * @return array{snippet: Snippet}
     */
    public function aSnippetAPageShows(string $title): array
    {
        $snippet = new Snippet();
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setBlockTree(new StoredTree(null, [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a', 'snippet', 1, fields: ['snippet' => $snippet->getUuid()])]));
        $page->publish(new \DateTimeImmutable());
        $this->showingSnippets = [$page];

        return ['snippet' => $snippet];
    }

    /**
     * A page edited last, with a block of a type the code no longer has, which the lists' Needs attention filter finds
     *
     * @return array{}
     */
    public function anEntryNeedingAttention(string $title): array
    {
        $page = $this->aPageWithABlockOfATypeTheCodeNoLongerHas('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a')['page'];
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $this->needingAttention = [$page];

        return [];
    }

    /**
     * Pages the repository finds a draft in progress for, the one edited last first: here, pages never published
     *
     * @return array{}
     */
    public function draftsInProgress(string ...$titles): array
    {
        $this->withDraft = array_map(fn (string $title): Page => $this->aPage($title)['page'], array_values($titles));

        return [];
    }

    /**
     * A page shown from and until these times, in UTC. Now is 2026-11-17 12:00 UTC
     *
     * @return array{}
     */
    public function aPageShown(string $title, ?string $from, ?string $until): array
    {
        $page = $this->aPage($title)['page'];
        $utc = new \DateTimeZone('UTC');
        $page->setDateWindow(new DateWindow(null === $from ? null : new \DateTimeImmutable($from, $utc), null === $until ? null : new \DateTimeImmutable($until, $utc)));
        $this->dated[] = $page;

        return [];
    }

    /**
     * @return array{}
     */
    public function imagesMissingAltText(int $count): array
    {
        $this->missingAltText = $count;

        return [];
    }

    /**
     * The store's channels WEB, in en_US and da_DK, and B2B, in en_US, with entries in each, complete in some locales
     *
     * @param array<string, int> $completeInWeb by locale
     * @param array<string, int> $completeInB2b by locale
     *
     * @return array{}
     */
    public function entriesCompleteIn(int $inWeb, array $completeInWeb, int $inB2b, array $completeInB2b): array
    {
        $this->completeness = ['WEB' => ['entries' => $inWeb, 'complete' => $completeInWeb], 'B2B' => ['entries' => $inB2b, 'complete' => $completeInB2b]];

        return [];
    }

    /**
     * The Overview's panels load the rows of the entries they list in one go, rather than one by one as each row prints
     *
     * @test
     */
    public function it_loads_the_rows_of_the_entries_an_overview_panel_lists_in_one_go(): void
    {
        $about = $this->aPage('About us')['page'];
        $contact = $this->aPage('Contact')['page'];
        $this->withDraft = [$about, $contact];
        $drafts = $this->prophesize(DraftRepositoryInterface::class);
        $drafts->findByEntries([$about, $contact])->willReturn([])->shouldBeCalledOnce();
        $entryRows = $this->entryRows($drafts->reveal());
        $runtime = $this->overviewRuntime($entryRows, $this->prophesize(EntryRepositoryInterface::class), $this->problemFinder());

        self::assertSame([$about, $contact], $runtime->draftsInProgress());
        self::assertSame('About us', $entryRows->get($about)->title);
        self::assertSame('Contact', $entryRows->get($contact)->title);
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

        $entryRows = $this->entryRows($drafts->reveal());
        $oldSlugs = $this->prophesize(OldSlugRepositoryInterface::class);
        $oldSlugs->findByEntry(Argument::any())->willReturn([]);
        $entryUrls = new EntryUrls($drafts->reveal(), self::contentTypes(), $urlGenerator->reveal(), $oldSlugs->reveal());
        $revisions = $this->prophesize(RevisionRepositoryInterface::class);
        $revisions->findByEntry(Argument::type(Page::class))->willReturn([
            self::revision('2026-11-14 10:00:00', 'Jane Roe'),
            self::revision('2026-11-01 10:00:00', 'John Doe'),
        ]);
        $entryShareLinks = $this->entryShareLinks();
        $translationChips = $this->translationChips();
        $whereItShows = $this->whereItShows();
        $runtime = new AdminRuntime(self::contentTypes(), $this->customerGroups->reveal(), $entryRows, $translationChips);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::any())->willReturnArgument(0);
        $problemFinder = $this->problemFinder();
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

        $placements = $this->prophesize(PlacementRepositoryInterface::class);
        $placed = &$this->placements;
        $placements->findBySnippet(Argument::any())->will(static function (array $arguments) use (&$placed): array {
            return array_values(array_filter($placed, static fn (Placement $placement): bool => $placement->getSnippet() === ($arguments[0] ?? null)));
        });
        $placements->findBySlots(Argument::any())->will(static function () use (&$placed): array {
            return $placed;
        });
        $declaredContext = $this->prophesize(DeclaredContextInterface::class);
        $declaredContext->toPlace(Argument::type(SnippetInterface::class))->will(static fn (array $arguments): array => $arguments[0] instanceof SnippetInterface ? $arguments[0]->getContext() : []);
        $conditionKinds = Conditions::registry();
        $entryPlacements = new EntryPlacements(
            $placements->reveal(),
            new SlotRegistry(['shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null]]),
            $declaredContext->reveal(),
            $conditionKinds,
            new PageScopeSummaries($conditionKinds, Conditions::fieldHandlers(), new ServiceLocator([]), new IdentityTranslator()),
            new CMSHomepageChannels($homepages->reveal(), $channels->reveal()),
        );

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $showing = &$this->showingSnippets;
        $entries->findReferencing(Argument::any())->will(static function () use (&$showing): array {
            return $showing;
        });
        $snippetBlocks = new BlockTypeRegistry(['snippet' => SnippetBlock::class], new FieldReader());
        $snippetUses = new SnippetUses(
            $entries->reveal(),
            $workingCopies->reveal(),
            new ReferenceFinder(new TreeValues($snippetBlocks, new LayoutRegistry([], new FieldReader())), new FieldHandlerLocator(new ServiceLocator([
                SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
            ]))),
            self::contentTypes(),
        );

        $overview = new EntryOverviewRuntime($entryUrls, $channelHomepages, $problemFinder, $revisions->reveal(), $entryShareLinks, $whereItShows, $entryPlacements, $snippetUses);

        $overviewPanels = $this->overviewRuntime($entryRows, $entries, $problemFinder);
        $pageScopeEditor = $this->pageScopeEditorRuntime();

        return [new FactoryRuntimeLoader([
            AdminRuntime::class => static fn (): AdminRuntime => $runtime,
            EntryOverviewRuntime::class => static fn (): EntryOverviewRuntime => $overview,
            OverviewRuntime::class => static fn (): OverviewRuntime => $overviewPanels,
            PageScopeEditorRuntime::class => static fn (): PageScopeEditorRuntime => $pageScopeEditor,
        ])];
    }

    /**
     * The rows of the lists and the Overview, of entries in en_US, where a page's URL is /en_US/about-us
     */
    private function entryRows(DraftRepositoryInterface $drafts): EntryRows
    {
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

        return new EntryRows(
            $drafts,
            self::contentTypes(),
            new StatusLabeler(new MockClock(), new Availability()),
            $urlGenerator->reveal(),
            $translationLocaleProvider->reveal(),
            $localeContext->reveal(),
            $scheduledPublishes->reveal(),
            new MockClock(),
            $problemCounts->reveal(),
        );
    }

    /**
     * Finds blocks of a type the code no longer has
     */
    private function problemFinder(): ProblemFinder
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::any())->willReturnArgument(0);

        return new ProblemFinder([new UnknownBlockType(new BlockTypeRegistry([], new FieldReader()))], $workingCopies->reveal());
    }

    /**
     * The repository's paginator is a Pagerfanta<mixed>, and the lowest Pagerfanta's template isn't covariant, so the
     * rows go in as mixed
     *
     * @param list<mixed> $rows
     *
     * @return Pagerfanta<mixed>
     */
    private static function paginator(array $rows): Pagerfanta
    {
        return new Pagerfanta(new ArrayAdapter($rows));
    }

    /**
     * The Overview's panels, which list what the --DATA-- sections set. Now is 2026-11-17 12:00 UTC
     *
     * @param ObjectProphecy<EntryRepositoryInterface> $entries
     */
    private function overviewRuntime(EntryRows $entryRows, ObjectProphecy $entries, ProblemFinder $problemFinder): OverviewRuntime
    {
        // Prophecy binds a closure to the double, so a static one reads what a --DATA-- section sets through a reference
        $needingAttention = &$this->needingAttention;
        $entries->createNeedingAttentionPaginator(Argument::any())->will(static function () use (&$needingAttention): Pagerfanta {
            return self::paginator($needingAttention);
        });
        $withDraft = &$this->withDraft;
        $entries->findDraftsInProgress(OverviewRuntime::DRAFTS_IN_PROGRESS)->will(static function () use (&$withDraft): array {
            return $withDraft;
        });
        $dated = &$this->dated;
        $entries->findStartingAfter(Argument::cetera())->will(static function (array $arguments) use (&$dated): array {
            return array_values(array_filter($dated, static fn (EntryInterface $entry): bool => $entry->getDateWindow()->from > $arguments[0]));
        });
        $entries->findEndingAfter(Argument::cetera())->will(static function (array $arguments) use (&$dated): array {
            return array_values(array_filter($dated, static fn (EntryInterface $entry): bool => null !== $entry->getDateWindow()->until && $entry->getDateWindow()->until > $arguments[0]));
        });
        $scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $scheduledPublishes->findComingUp(Argument::cetera())->willReturn([]);
        $problemCounts = $this->prophesize(ProblemCountProviderInterface::class);

        $missingAltText = &$this->missingAltText;
        $mediaItems = $this->prophesize(MediaItemRepositoryInterface::class);
        // Images missing alt text in any of the store's locales, the default first
        $mediaItems->createLibraryPaginator(Argument::that(static fn (MediaFilter $filter): bool => ['en_US', 'da_DK'] === $filter->missingAltIn))->will(static function () use (&$missingAltText): Pagerfanta {
            return new Pagerfanta(new ArrayAdapter(array_fill(0, $missingAltText, null)));
        });

        $english = new Locale();
        $english->setCode('en_US');
        $danish = new Locale();
        $danish->setCode('da_DK');
        $web = new Channel();
        $web->setCode('WEB');
        $web->setName('Web store');
        $web->addLocale($danish);
        $web->addLocale($english);
        $web->setDefaultLocale($english);
        $b2b = new Channel();
        $b2b->setCode('B2B');
        $b2b->setName('Wholesale');
        $b2b->addLocale($english);
        $b2b->setDefaultLocale($english);
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['id' => 'ASC'])->willReturn([$web, $b2b]);
        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_US');

        $completeness = &$this->completeness;
        $completenessQuery = $this->prophesize(CompletenessQueryInterface::class);
        $completenessQuery->count(['WEB' => ['en_US', 'da_DK'], 'B2B' => ['en_US']])->will(static function () use (&$completeness): array {
            return $completeness;
        });

        return new OverviewRuntime(
            new ComingUp($scheduledPublishes->reveal(), $entries->reveal(), new MockClock('2026-11-17 12:00:00 UTC')),
            $entryRows,
            $entries->reveal(),
            new NeedsAttention($entries->reveal(), $problemCounts->reveal(), $problemFinder),
            $mediaItems->reveal(),
            new MediaLocales(new ChannelLocales($channels->reveal()), $translationLocaleProvider->reveal(), $this->prophesize(RepositoryInterface::class)->reveal()),
            new TranslationCompleteness($channels->reveal(), $completenessQuery->reveal()),
        );
    }

    /**
     * The page scope editor of a store whose only condition kind that reads the host page is on_product_page, and whose
     * builder has one string of its own, besides an admin string
     */
    private function pageScopeEditorRuntime(): PageScopeEditorRuntime
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['setono_sylius_cms.builder.page_scope.edit' => 'Edit page scope', 'setono_sylius_cms.ui.save' => 'Save'], 'en_US');
        $blockTypes = new BlockTypeRegistry([], new FieldReader());
        $uiStrings = new UiStrings($translator, $translator);
        $routes = new RouteCollection();
        $routes->add('setono_sylius_cms_admin_api_search', new Route('/admin/cms/api/search/{kind}'));

        return new PageScopeEditorRuntime(
            new RegistryExporter(
                $blockTypes,
                self::contentTypes(),
                new NestingRules($blockTypes, self::contentTypes()),
                Conditions::fieldHandlers(),
                $translator,
                $uiStrings,
                Conditions::registry(['on_product_page' => new OnProductPage()]),
                new LayoutRegistry([], new FieldReader()),
                new MediaLimits(new UploadLimit(2 * 1024 * 1024, 8 * 1024 * 1024), new MediaTypes([])),
                Links::registry(),
                Sources::registry([]),
                new ContextKeyRegistry(['product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product']]),
                new SlotRegistry([]),
                Bindings::registry(),
            ),
            $uiStrings,
            new UrlGenerator($routes, new RequestContext()),
        );
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
            new ChannelLocales($channels->reveal()),
            new Availability(),
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
            new ShareLinkUrlGenerator(
                $workingCopies->reveal(),
                self::contentTypes(),
                $channels->reveal(),
                new UrlGenerator($routes, new RequestContext(host: 'admin.example.com', scheme: 'https')),
                SnippetPreviews::hostPages($this->prophesize(PlacementRepositoryInterface::class)->reveal()),
                SnippetPreviews::samples(),
                SnippetPreviews::renderContexts(),
            ),
            $channels->reveal(),
            $workingCopies->reveal(),
            self::contentTypes(),
            new SlotRegistry([]),
        );
    }

    private function runtime(): AdminRuntime
    {
        return new AdminRuntime(
            self::contentTypes(),
            $this->customerGroups->reveal(),
            new EntryRows(
                $this->prophesize(DraftRepositoryInterface::class)->reveal(),
                self::contentTypes(),
                new StatusLabeler(new MockClock(), new Availability()),
                $this->prophesize(UrlGeneratorInterface::class)->reveal(),
                $this->prophesize(TranslationLocaleProviderInterface::class)->reveal(),
                $this->prophesize(LocaleContextInterface::class)->reveal(),
                $this->prophesize(ScheduledPublishRepositoryInterface::class)->reveal(),
                new MockClock(),
                $this->prophesize(ProblemCountProviderInterface::class)->reveal(),
            ),
            $this->translationChips(),
        );
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
