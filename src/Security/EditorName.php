<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Security;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * How the admin names an editor to colleagues: in the builder's presence and as who last edited an entry
 */
final class EditorName
{
    private function __construct()
    {
    }

    /**
     * The signed-in admin user's name, or null when no one is signed in, such as in the Messenger worker
     */
    public static function signedIn(TokenStorageInterface $tokenStorage): ?string
    {
        $editor = $tokenStorage->getToken()?->getUser();

        return null === $editor ? null : self::of($editor);
    }

    /**
     * An admin user's full name, or else their identifier
     */
    public static function of(UserInterface $editor): string
    {
        $name = $editor instanceof AdminUserInterface ? trim(sprintf('%s %s', $editor->getFirstName() ?? '', $editor->getLastName() ?? '')) : '';

        return '' === $name ? $editor->getUserIdentifier() : $name;
    }
}
