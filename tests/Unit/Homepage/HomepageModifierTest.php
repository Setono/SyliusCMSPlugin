<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Homepage;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifier;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\BlockTreeValidatorTest;

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

    /**
     * A page can only become the homepage of a channel it's in, as shoppers of any other channel never see it, while one
     * it has left stays its homepage. See "Homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_refuses_to_make_a_page_the_homepage_of_a_channel_it_will_not_be_in_unless_it_already_is_its_homepage(): void
    {
        $page = new Page();
        $page->addHomepage(self::homepage($page, 'WEB'));

        $this->modifier()->checkHomepageOf($page, ['WEB', 'B2B'], ['B2B']);

        try {
            $this->modifier()->checkHomepageOf($page, ['WEB', 'MOBILE', 'B2B', 'OUTLET', 'MOBILE'], ['B2B']);
            self::fail('The homepage was not refused');
        } catch (InvalidEntrySettingsException $e) {
            self::assertSame('A page can only be the homepage of a channel it\'s in, and this page isn\'t in MOBILE, OUTLET.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_runs_the_writes_in_a_transaction_of_the_manager(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $written = false;
        $manager->wrapInTransaction(Argument::type(\Closure::class))->will(static function (array $arguments): mixed {
            $writes = $arguments[0] ?? null;
            self::assertInstanceOf(\Closure::class, $writes);

            return $writes();
        })->shouldBeCalledOnce();

        $this->modifier()->inTransaction($manager->reveal(), static function () use (&$written): void {
            $written = true;
        });

        self::assertTrue($written);
    }

    /**
     * Two saves that make different pages a channel's homepage at the same moment both find no other page to take it
     * off, so the second one's row clashes with the first one's on the unique channel code, which the database names
     *
     * @test
     */
    public function it_tells_that_another_page_just_became_the_channels_homepage_when_the_homepage_rows_clash(): void
    {
        $clash = self::uniqueConstraintViolation("SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"setono_sylius_cms__homepage_channel_uniq\"");
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->wrapInTransaction(Argument::any())->willThrow($clash);

        try {
            $this->modifier()->inTransaction($manager->reveal(), static function (): void {
            });
            self::fail('The clash was not turned into a message');
        } catch (InvalidEntrySettingsException $e) {
            self::assertSame('Another page just became this channel\'s homepage. Choose the channel again to make this page its homepage instead.', $e->getMessage());
            self::assertSame($clash, $e->getPrevious());
        }
    }

    /**
     * @test
     */
    public function it_leaves_a_clash_on_another_unique_key_as_it_is(): void
    {
        $clash = self::uniqueConstraintViolation("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '42-WEB' for key 'setono_sylius_cms__entry_channel.setono_sylius_cms__entry_channel_uniq'");
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->wrapInTransaction(Argument::any())->willThrow($clash);

        try {
            $this->modifier()->inTransaction($manager->reveal(), static function (): void {
            });
            self::fail('The clash was not thrown');
        } catch (UniqueConstraintViolationException $e) {
            self::assertSame($clash, $e);
        }
    }

    /**
     * What DBAL throws when a write breaks a unique key, with the database's message, which names the key
     */
    private static function uniqueConstraintViolation(string $message): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(new class($message) extends \Exception implements DriverException {
            public function getSQLState(): string
            {
                return '23000';
            }
        }, null);
    }

    private function modifier(): HomepageModifier
    {
        return Homepages::modifier($this->repository->reveal(), BlockTreeValidatorTest::translator(), $this->factory->reveal());
    }

    private static function homepage(Page $page, string $channelCode): HomepageInterface
    {
        return Homepages::factory()->createForPage($page, $channelCode);
    }
}
