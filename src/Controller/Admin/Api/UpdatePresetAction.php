<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetDetailsException;
use Setono\SyliusCMSPlugin\Preset\PresetDetails;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renames or recategorises an editor preset, at once, from the builder's Presets tab: the body holds what changes of
 * {"name": …, "category": …, "description": …}, an empty category or description for none. See "Editor presets" in
 * docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final readonly class UpdatePresetAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private EditorPresetLookup $lookup,
        private EditorPresetSaverInterface $saver,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request, $uuid): JsonResponse {
            $preset = $this->lookup->editorPreset($uuid);

            try {
                $details = PresetDetails::of($preset)->changedBy($request->toArray());
            } catch (InvalidPresetDetailsException $e) {
                return BuilderRequest::badRequest($e->translatable->trans($this->translator));
            } catch (JsonException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            return new JsonResponse(['preset' => PresetAnswer::of($this->saver->save($details->applyTo($preset)), $this->translator)]);
        });
    }
}
