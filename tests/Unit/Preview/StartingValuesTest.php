<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preview\StartingValues;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Where Shown to and Date start in the builder's preview, and what a share link fixes them at.
 * See "Shown to and Date" in docs/spec/targeting.md
 */
final class StartingValuesTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> */
    private ObjectProphecy $customerGroups;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 10:00:00 UTC');
        $this->customerGroups = $this->prophesize(RepositoryInterface::class);
        // In the order of their names, as Shown to lists them, so b2c comes before b2b
        $this->customerGroups->findBy([], ['name' => 'ASC'])->willReturn([
            self::group('b2c'),
            self::group(null),
            self::group('b2b'),
        ]);
    }

    /**
     * @test
     */
    public function shown_to_starts_as_guest_when_the_audience_is_everyone(): void
    {
        $this->customerGroups->findBy()->shouldNotBeCalled();

        self::assertSame('@guest', $this->startingValues()->shownTo(new Page()));
    }

    /**
     * @test
     *
     * @dataProvider audiences
     */
    public function shown_to_starts_as_guest_when_the_audience_allows_guests_and_otherwise_as_the_first_group_it_allows(Audience $audience, string $shownTo): void
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setAudience($page, $audience);

        self::assertSame($shownTo, $this->startingValues()->shownTo($page));
    }

    /**
     * @return iterable<string, array{Audience, string}>
     */
    public static function audiences(): iterable
    {
        yield 'only guests and a group' => [Audience::only('b2b', '@guest'), '@guest'];
        yield 'only customers without a group and a group' => [Audience::only('b2b', '@no_group'), '@no_group'];
        yield 'only one group' => [Audience::only('b2b'), 'b2b'];
        yield 'only groups, the first by name' => [Audience::only('b2b', 'b2c'), 'b2c'];
        yield 'everyone except groups' => [Audience::except('b2b', 'b2c'), '@guest'];
        yield 'everyone except guests' => [Audience::except('@guest'), '@no_group'];
        yield 'everyone except guests and customers without a group' => [Audience::except('@guest', '@no_group'), 'b2c'];
        yield 'everyone except guests, customers without a group and the first group by name' => [Audience::except('@guest', '@no_group', 'b2c'), 'b2b'];
    }

    /**
     * A deleted group's code matches nobody, and Shown to doesn't offer it
     *
     * @test
     */
    public function shown_to_starts_as_guest_when_the_audience_allows_no_one_shown_to_offers(): void
    {
        $nobody = new Page();
        ChannelsAndAudience::modifier()->setAudience($nobody, Audience::except('@guest', '@no_group', 'b2b', 'b2c'));
        $deleted = new Page();
        ChannelsAndAudience::modifier()->setAudience($deleted, Audience::only('deleted'));

        self::assertSame('@guest', $this->startingValues()->shownTo($nobody));
        self::assertSame('@guest', $this->startingValues()->shownTo($deleted));
    }

    /**
     * @test
     */
    public function date_starts_as_now_without_a_date_window(): void
    {
        self::assertNull($this->startingValues()->date(new Page()));
    }

    /**
     * @test
     */
    public function date_starts_as_the_start_of_the_date_window_when_that_is_in_the_future(): void
    {
        $page = new Page();
        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2026-11-14 10:00:01 UTC'), new \DateTimeImmutable('2026-12-01 00:00:00 UTC')));

        self::assertEquals(new \DateTimeImmutable('2026-11-14 10:00:01 UTC'), $this->startingValues()->date($page));
    }

    /**
     * @test
     *
     * @dataProvider windowsStartedOrWithoutAStart
     */
    public function date_starts_as_now_when_the_date_window_has_started_or_has_no_start(DateWindow $window): void
    {
        $page = new Page();
        $page->setDateWindow($window);

        self::assertNull($this->startingValues()->date($page));
    }

    /**
     * @return iterable<string, array{DateWindow}>
     */
    public static function windowsStartedOrWithoutAStart(): iterable
    {
        yield 'started now' => [new DateWindow(new \DateTimeImmutable('2026-11-14 10:00:00 UTC'))];
        yield 'started before' => [new DateWindow(new \DateTimeImmutable('2026-11-01 00:00:00 UTC'), new \DateTimeImmutable('2026-12-01 00:00:00 UTC'))];
        yield 'ended' => [new DateWindow(null, new \DateTimeImmutable('2026-11-01 00:00:00 UTC'))];
        yield 'ending' => [new DateWindow(null, new \DateTimeImmutable('2026-12-01 00:00:00 UTC'))];
    }

    private function startingValues(): StartingValues
    {
        return new StartingValues($this->customerGroups->reveal(), $this->clock);
    }

    private static function group(?string $code): CustomerGroupInterface
    {
        $group = new CustomerGroup();
        $group->setCode($code);

        return $group;
    }
}
