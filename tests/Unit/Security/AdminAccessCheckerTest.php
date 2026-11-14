<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminAccessCheckerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lets_an_admin_user_through(): void
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(true)->shouldBeCalledOnce();

        (new AdminAccessChecker($authorizationChecker->reveal()))->denyUnlessAdmin();
    }

    /**
     * @test
     */
    public function it_denies_anyone_else(): void
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        (new AdminAccessChecker($authorizationChecker->reveal()))->denyUnlessAdmin();
    }
}
