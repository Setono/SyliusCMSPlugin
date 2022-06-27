<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadAssetAction extends AbstractAssetAction
{
    public function __invoke(Request $request, int $id): Response
    {
        return $this->getPreparedResponse($id, true);
    }
}
