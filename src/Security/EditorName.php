<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Security;

use Sylius\Component\Core\Model\AdminUserInterface;
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
     * An admin user's full name, or else their identifier
     */
    public static function of(UserInterface $editor): string
    {
        $name = $editor instanceof AdminUserInterface ? trim(sprintf('%s %s', $editor->getFirstName() ?? '', $editor->getLastName() ?? '')) : '';

        return '' === $name ? $editor->getUserIdentifier() : $name;
    }
}
