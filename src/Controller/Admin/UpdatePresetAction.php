<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetDetailsException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetDetails;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Presets screen's Edit of an editor preset: renames and recategorises it, and changes its description, at once.
 * See "Presets" in docs/spec/admin.md
 */
final readonly class UpdatePresetAction
{
    public function __construct(
        private PresetsScreenRequest $screenRequest,
        private EditorPresetSaverInterface $saver,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->screenRequest->submit($request, $uuid, function (Preset $preset) use ($request): array {
            try {
                $details = PresetDetails::of($preset)->changedBy(array_intersect_key($request->request->all(), array_flip(['name', 'category', 'description'])));
            } catch (InvalidPresetDetailsException $e) {
                return ['error', $e->translatable->trans($this->translator), true];
            }

            $this->saver->save($details->applyTo($preset));

            return ['success', 'setono_sylius_cms.preset_saved', true];
        });
    }
}
