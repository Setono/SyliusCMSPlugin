<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The builder's JSON endpoints check this CSRF token on every request that changes something.
 * The builder page hands it to the builder app, which sends it in the X-CSRF-Token header
 */
final readonly class BuilderCsrfToken
{
    public const HEADER = 'X-CSRF-Token';

    private const ID = 'setono_sylius_cms_builder';

    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function value(): string
    {
        return $this->csrfTokenManager->getToken(self::ID)->getValue();
    }

    public function isValid(Request $request): bool
    {
        $value = $request->headers->get(self::HEADER);

        return null !== $value && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::ID, $value));
    }
}
