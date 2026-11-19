<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The editor preset an endpoint's URL names by its UUID. Code presets are read-only, so they're never found here
 *
 * @internal
 */
final readonly class EditorPresetLookup
{
    public function __construct(private PresetProviderInterface $presets)
    {
    }

    /**
     * @throws NotFoundHttpException when there's no such editor preset
     */
    public function editorPreset(string $uuid): Preset
    {
        $preset = $this->presets->find($uuid);
        if (null === $preset || $preset->isFromCode()) {
            throw new NotFoundHttpException(sprintf('There is no editor preset %s', $uuid));
        }

        return $preset;
    }
}
