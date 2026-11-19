<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Form\Type\DeletePresetType;
use Setono\SyliusCMSPlugin\Preset\EditorPresetDeleterInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Presets screen's Delete of an editor preset, at once. The entries it was inserted into stay as they are. See
 * "Presets" in docs/spec/admin.md
 */
final readonly class DeletePresetAction
{
    public function __construct(
        private PresetsScreenRequest $screenRequest,
        private EditorPresetDeleterInterface $deleter,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->screenRequest->submit($request, $uuid, DeletePresetType::class, function (Preset $preset): array {
            $this->deleter->delete($preset->id());

            return ['success', 'setono_sylius_cms.preset_deleted'];
        });
    }
}
