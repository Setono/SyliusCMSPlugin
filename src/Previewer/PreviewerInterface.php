<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use Setono\SyliusCMSPlugin\Model\AssetInterface;

interface PreviewerInterface
{
    public function preview(AssetInterface $asset): Preview;

    /**
     * Returns true if the previewer supports the given asset
     */
    public function supports(AssetInterface $asset): bool;
}
