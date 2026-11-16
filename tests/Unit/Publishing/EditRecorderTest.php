<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class EditRecorderTest extends TestCase
{
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
    }

    /**
     * @test
     */
    public function it_records_the_signed_in_admin_user_by_name_and_now(): void
    {
        $this->signIn(self::admin('jdoe', 'John', 'Doe'));
        $page = new Page();

        $this->recorder()->record($page);

        self::assertSame('John Doe', $page->getLastEditedBy());
        self::assertEquals(new \DateTimeImmutable('2026-11-16T12:00:00Z'), $page->getLastEditedAt());
    }

    /**
     * @test
     */
    public function it_records_a_user_without_a_name_by_their_identifier(): void
    {
        $this->signIn(new InMemoryUser('robot', null));
        $page = new Page();

        $this->recorder()->record($page);

        self::assertSame('robot', $page->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_records_when_but_not_who_when_no_one_is_signed_in(): void
    {
        $page = new Page();
        $page->setLastEdited(new \DateTimeImmutable('2026-11-01T00:00:00Z'), 'Jane Roe');

        $this->recorder()->record($page);

        self::assertNull($page->getLastEditedBy());
        self::assertEquals(new \DateTimeImmutable('2026-11-16T12:00:00Z'), $page->getLastEditedAt());
    }

    private function recorder(): EditRecorder
    {
        return new EditRecorder($this->tokenStorage, new MockClock('2026-11-16 13:00:00', 'Europe/Copenhagen'));
    }

    private function signIn(UserInterface $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'admin', ['ROLE_ADMINISTRATION_ACCESS']));
    }

    private static function admin(string $username, string $firstName, string $lastName): UserInterface
    {
        $admin = self::newAdmin();
        $admin->setUsername($username);
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
