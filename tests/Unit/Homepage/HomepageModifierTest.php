<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Homepage;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifier;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;

final class HomepageModifierTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<HomepageFactoryInterface> */
    private ObjectProphecy $factory;

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $repository;

    protected function setUp(): void
    {
        $this->factory = $this->prophesize(HomepageFactoryInterface::class);
        $this->factory->createForPage(Argument::cetera())->will(static function (array $arguments): HomepageInterface {
            [$page, $channelCode] = $arguments;
            self::assertInstanceOf(PageInterface::class, $page);
            self::assertIsString($channelCode);

            return Homepages::factory()->createForPage($page, $channelCode);
        });
        $this->repository = $this->prophesize(HomepageRepositoryInterface::class);
        $this->repository->findByChannelCodes(Argument::any())->willReturn([]);
    }

    /**
     * @test
     */
    public function it_creates_the_rows_of_the_channels_through_the_homepage_factory(): void
    {
        $page = new Page();

        $this->modifier()->setHomepageOf($page, ['WEB', 'B2B', 'WEB']);

        self::assertSame(['B2B', 'WEB'], $page->getHomepageChannelCodes());
        $this->factory->createForPage($page, 'WEB')->shouldHaveBeenCalledOnce();
        $this->factory->createForPage($page, 'B2B')->shouldHaveBeenCalledOnce();
    }

    /**
     * Doctrine removes rows after it inserts them, so a new row for a channel that stays would clash with the old one's
     * unique channel code
     *
     * @test
     */
    public function it_keeps_the_rows_of_the_channels_that_stay_and_removes_the_others(): void
    {
        $page = new Page();
        $web = self::homepage($page, 'WEB');
        $b2b = self::homepage($page, 'B2B');
        $page->addHomepage($web);
        $page->addHomepage($b2b);

        $this->modifier()->setHomepageOf($page, ['WEB', 'MOBILE']);

        self::assertSame(['MOBILE', 'WEB'], $page->getHomepageChannelCodes());
        self::assertTrue($page->hasHomepage($web));
        self::assertFalse($page->hasHomepage($b2b));
        $this->factory->createForPage($page, 'WEB')->shouldNotHaveBeenCalled();
        $this->factory->createForPage($page, 'MOBILE')->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_makes_the_page_the_homepage_of_no_channel(): void
    {
        $page = new Page();
        $page->addHomepage(self::homepage($page, 'WEB'));

        $this->modifier()->setHomepageOf($page, []);

        self::assertSame([], $page->getHomepageChannelCodes());
    }

    /**
     * A project's own page model implements PageInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_changes_the_homepage_rows_of_any_page_through_its_interface(): void
    {
        $page = $this->prophesize(PageInterface::class);
        $page->getHomepages()->willReturn(new ArrayCollection());
        $revealed = $page->reveal();

        $this->modifier()->setHomepageOf($revealed, ['WEB']);

        $page->addHomepage(Argument::that(
            static fn (HomepageInterface $homepage): bool => $revealed === $homepage->getPage() && 'WEB' === $homepage->getChannelCode(),
        ))->shouldHaveBeenCalledOnce();
    }

    /**
     * A channel has at most one homepage, so choosing a page for a channel takes the channel off any other page
     *
     * @test
     */
    public function it_takes_the_channels_off_the_other_pages_that_are_their_homepages(): void
    {
        $page = new Page();
        $about = new Page();
        $aboutWeb = self::homepage($about, 'WEB');
        $aboutMobile = self::homepage($about, 'MOBILE');
        $about->addHomepage($aboutWeb);
        $about->addHomepage($aboutMobile);
        $sale = new Page();
        $saleB2b = self::homepage($sale, 'B2B');
        $sale->addHomepage($saleB2b);
        $this->repository->findByChannelCodes(['WEB', 'B2B', 'MOBILE'])->willReturn([$saleB2b, $aboutMobile, $aboutWeb]);

        $takenFrom = $this->modifier()->takeOffOtherPages($page, ['WEB', 'B2B', 'WEB', 'MOBILE']);

        self::assertSame([$sale, $about], $takenFrom, 'Each page once, in the order of the rows');
        self::assertSame([], $about->getHomepageChannelCodes());
        self::assertSame([], $sale->getHomepageChannelCodes());
    }

    /**
     * @test
     */
    public function it_leaves_the_pages_own_rows_alone_when_taking_channels_off_the_others(): void
    {
        $page = new Page();
        $web = self::homepage($page, 'WEB');
        $page->addHomepage($web);
        $sale = new Page();
        $saleB2b = self::homepage($sale, 'B2B');
        $sale->addHomepage($saleB2b);
        $this->repository->findByChannelCodes(['WEB', 'B2B'])->willReturn([$web, $saleB2b]);

        self::assertSame([$sale], $this->modifier()->takeOffOtherPages($page, ['WEB', 'B2B']));
        self::assertTrue($page->hasHomepage($web));
        self::assertFalse($sale->hasHomepage($saleB2b));
    }

    /**
     * @test
     */
    public function it_takes_the_channel_codes_from_any_iterable(): void
    {
        $page = new Page();
        $sale = new Page();
        $saleB2b = self::homepage($sale, 'B2B');
        $sale->addHomepage($saleB2b);
        $this->repository->findByChannelCodes(['B2B'])->willReturn([$saleB2b]);

        self::assertSame([$sale], $this->modifier()->takeOffOtherPages($page, self::generate('B2B')));
        $this->modifier()->setHomepageOf($page, self::generate('B2B', 'WEB'));

        self::assertSame(['B2B', 'WEB'], $page->getHomepageChannelCodes());
    }

    /**
     * @return \Generator<int, string>
     */
    private static function generate(string ...$codes): \Generator
    {
        foreach ($codes as $code) {
            yield $code;
        }
    }

    /**
     * @test
     */
    public function it_looks_nothing_up_to_take_off_no_channel(): void
    {
        self::assertSame([], $this->modifier()->takeOffOtherPages(new Page(), []));

        $this->repository->findByChannelCodes(Argument::any())->shouldNotHaveBeenCalled();
    }

    private function modifier(): HomepageModifier
    {
        return new HomepageModifier($this->factory->reveal(), $this->repository->reveal());
    }

    private static function homepage(Page $page, string $channelCode): HomepageInterface
    {
        return Homepages::factory()->createForPage($page, $channelCode);
    }
}
