<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\RegistryExporter;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class RegistryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private RegistryExporter $registryExporter,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return new JsonResponse($this->registryExporter->export());
    }
}
