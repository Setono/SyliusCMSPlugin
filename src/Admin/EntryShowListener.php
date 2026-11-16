<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;

/**
 * Listens to the entry resource's show (setono_sylius_cms.entry.show), which the entry overview page renders through.
 * It checks the admin user again, as every CMS admin page does, so a route imported under another prefix doesn't open
 * the overview page up
 */
final readonly class EntryShowListener
{
    public function __construct(private AdminAccessChecker $adminAccessChecker)
    {
    }

    public function __invoke(): void
    {
        $this->adminAccessChecker->denyUnlessAdmin();
    }
}
