<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use Setono\SyliusCMSPlugin\Model\AssetInterface;

final class CatchAllPreviewer implements PreviewerInterface
{
    public function preview(AssetInterface $asset): Preview
    {
        return Preview::createUnavailablePreview();
    }

    public function supports(AssetInterface $asset): bool
    {
        return true;
    }
}
