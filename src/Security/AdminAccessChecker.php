<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The CMS admin lives under Sylius's admin path, so the admin firewall already guards it.
 * Its controllers check again, so a route imported under another prefix doesn't open them up
 */
final readonly class AdminAccessChecker
{
    private const ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    /**
     * @throws AccessDeniedException
     */
    public function denyUnlessAdmin(): void
    {
        if (!$this->authorizationChecker->isGranted(self::ROLE)) {
            throw new AccessDeniedException(sprintf('The CMS admin needs %s', self::ROLE));
        }
    }
}
