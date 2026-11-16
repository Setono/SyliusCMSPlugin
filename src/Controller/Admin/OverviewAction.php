<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The Content hub's home, which the Sylius admin menu's Content item opens. Its panels come with #275,
 * see "Overview" in docs/spec/admin.md
 */
final readonly class OverviewAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/overview.html.twig'));
    }
}
