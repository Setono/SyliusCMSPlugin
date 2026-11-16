<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The slug Sylius's slug generator makes of a title, which the create dialog fills the slug from while the editor types
 * the title, until the editor changes the slug. See "Slugs" in docs/spec/urls-and-seo.md
 */
final readonly class SlugAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return new JsonResponse(['slug' => $this->slugGenerator->generate((string) $request->query->get('title'))]);
    }
}
