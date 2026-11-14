<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Builder\PresenceTracker;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepositoryInterface;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class PresenceTrackerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryPresenceRepositoryInterface> */
    private ObjectProphecy $presences;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->presences = $this->prophesize(EntryPresenceRepositoryInterface::class);
        $this->clock = new MockClock('2026-11-14 10:00:30');
    }

    /**
     * @test
     */
    public function it_records_the_admin_user_by_name_now_and_forgets_who_was_last_seen_too_long_ago(): void
    {
        $page = new Page();
        $calls = [];
        $this->presences->removeSeenBefore($page, new \DateTimeImmutable('2026-11-14 10:00:10'))->will(static function () use (&$calls): void {
            $calls[] = 'forget';
        })->shouldBeCalledOnce();
        $this->presences->see($page, 'jane', 'Jane Roe', new \DateTimeImmutable('2026-11-14 10:00:30'))->will(static function () use (&$calls): void {
            $calls[] = 'see';
        })->shouldBeCalledOnce();

        $this->tracker()->see($page, self::admin('jane', 'Jane', 'Roe'));

        self::assertSame(['forget', 'see'], $calls);
    }

    /**
     * @test
     */
    public function it_answers_with_the_others_seen_lately(): void
    {
        $page = new Page();
        $this->presences->findNamesSeenSince($page, new \DateTimeImmutable('2026-11-14 10:00:10'), 'jane')->willReturn(['John Doe']);

        self::assertSame(['John Doe'], $this->tracker()->others($page, self::admin('jane', 'Jane', 'Roe')));
    }

    /**
     * @test
     *
     * @dataProvider provideNames
     */
    public function it_names_an_admin_user_by_their_full_name_or_else_their_identifier(UserInterface $editor, string $name): void
    {
        $page = new Page();
        $this->presences->removeSeenBefore($page, Argument::any())->shouldBeCalled();
        $this->presences->see($page, $editor->getUserIdentifier(), $name, Argument::any())->shouldBeCalledOnce();

        $this->tracker()->see($page, $editor);
    }

    /**
     * @return iterable<string, array{UserInterface, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'a first and a last name' => [self::admin('jane', 'Jane', 'Roe'), 'Jane Roe'];
        yield 'a first name only' => [self::admin('jane', 'Jane', null), 'Jane'];
        yield 'a last name only' => [self::admin('jane', null, 'Roe'), 'Roe'];
        yield 'no name' => [self::admin('jane', null, null), 'jane'];
        yield 'not an admin user of Sylius' => [new InMemoryUser('robot', null), 'robot'];
    }

    private function tracker(): PresenceTracker
    {
        return new PresenceTracker($this->presences->reveal(), $this->clock);
    }

    private static function admin(string $username, ?string $firstName, ?string $lastName): UserInterface
    {
        $admin = self::newAdmin();
        $admin->setUsername($username);
        $admin->setUsernameCanonical($username);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        self::assertInstanceOf(UserInterface::class, $admin);

        return $admin;
    }

    /**
     * Sylius's admin user is a Symfony user through a polyfill that static analysis doesn't follow
     */
    private static function newAdmin(): AdminUserInterface
    {
        return new AdminUser();
    }
}
