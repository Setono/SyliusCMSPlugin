<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryIndexListener;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\View\GridView;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EntryIndexListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AuthorizationCheckerInterface> */
    private ObjectProphecy $authorizationChecker;

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    private EntryRows $entryRows;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(true);
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);

        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_US');
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');
        $scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $scheduledPublishes->findByEntries(Argument::any())->willReturn([]);
        $problemCounts = $this->prophesize(ProblemCountProviderInterface::class);
        $problemCounts->getCounts(Argument::any())->willReturn([]);

        $this->entryRows = new EntryRows(
            $this->drafts->reveal(),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
            new StatusLabeler(new MockClock(), new Availability()),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $translationLocaleProvider->reveal(),
            $localeContext->reveal(),
            $scheduledPublishes->reveal(),
            new MockClock(),
            $problemCounts->reveal(),
        );
    }

    /**
     * @test
     */
    public function it_loads_the_rows_of_the_page_of_entries_a_list_shows(): void
    {
        $pages = [self::page('About us'), self::page('Contact'), self::page('Imprint'), self::page('Jobs')];
        $paginator = new Pagerfanta(new ArrayAdapter($pages));
        $paginator->setMaxPerPage(2);
        $paginator->setCurrentPage(2);
        // The page's drafts in one query, and no entry's alone
        $this->drafts->findByEntries([$pages[2], $pages[3]])->willReturn([])->shouldBeCalledOnce();
        $this->drafts->findByEntries([$pages[2]])->shouldNotBeCalled();
        $this->drafts->findByEntries([$pages[3]])->shouldNotBeCalled();

        $this->listener()(new ResourceControllerEvent(new GridView($paginator, self::grid(), new Parameters())));

        self::assertSame('Imprint', $this->entryRows->get($pages[2])->title);
        self::assertSame('Jobs', $this->entryRows->get($pages[3])->title);
    }

    /**
     * @test
     */
    public function it_loads_nothing_for_a_list_that_is_not_a_grid_of_pages(): void
    {
        $this->drafts->findByEntries(Argument::any())->shouldNotBeCalled();

        $this->listener()(new ResourceControllerEvent(new GridView([self::page('About us')], self::grid(), new Parameters())));
        $this->listener()(new ResourceControllerEvent([self::page('About us')]));
    }

    /**
     * @test
     */
    public function it_shows_the_lists_to_admin_users_only(): void
    {
        $this->authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(false);
        $this->drafts->findByEntries(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->listener()(new ResourceControllerEvent(new GridView(new Pagerfanta(new ArrayAdapter([self::page('About us')])), self::grid(), new Parameters())));
    }

    private function listener(): EntryIndexListener
    {
        return new EntryIndexListener(new AdminAccessChecker($this->authorizationChecker->reveal()), $this->entryRows);
    }

    private static function grid(): Grid
    {
        return Grid::fromCodeAndDriverConfiguration('setono_sylius_cms_admin_entry', 'doctrine/orm', []);
    }

    private static function page(string $title): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);

        return $page;
    }
}
