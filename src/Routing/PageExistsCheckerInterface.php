<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Symfony\Component\HttpFoundation\Request;

interface PageExistsCheckerInterface
{
    /**
     * Returns true if a page exists given the request
     */
    public function checkUrl(Request $request): bool;
}
