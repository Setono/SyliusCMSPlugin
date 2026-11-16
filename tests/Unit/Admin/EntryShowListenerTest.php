<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\EntryShowListener;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EntryShowListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lets_an_admin_user_see_the_overview_page(): void
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(true)->shouldBeCalled();

        (new EntryShowListener(new AdminAccessChecker($authorizationChecker->reveal())))();
    }

    /**
     * @test
     */
    public function it_denies_anyone_else(): void
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        (new EntryShowListener(new AdminAccessChecker($authorizationChecker->reveal())))();
    }
}
