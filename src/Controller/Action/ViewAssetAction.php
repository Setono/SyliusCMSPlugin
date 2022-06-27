<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ViewAssetAction extends AbstractAssetAction
{
    public function __invoke(Request $request, int $id): Response
    {
        $response = $this->getPreparedResponse($id);
        $response->setPublic();
        $response->setMaxAge(31_536_000); // 1 year
        $response->setSharedMaxAge(31_536_000); // 1 year

        return $response;
    }
}
