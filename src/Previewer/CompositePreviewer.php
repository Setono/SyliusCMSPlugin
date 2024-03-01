<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use LogicException;
use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusCMSPlugin\Model\AssetInterface;

/**
 * @extends CompositeService<PreviewerInterface>
 */
final class CompositePreviewer extends CompositeService implements PreviewerInterface
{
    public function preview(AssetInterface $asset): Preview
    {
        foreach ($this->services as $service) {
            if ($service->supports($asset)) {
                return $service->preview($asset);
            }
        }

        throw new LogicException('No previewer available for given asset. This should not be possible since we have a catch all previewer');
    }

    public function supports(AssetInterface $asset): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($asset)) {
                return true;
            }
        }

        return false;
    }
}
