<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Save as preset" then "Replace <name>" in the builder: replaces an editor preset's blocks with the selected ones, at
 * once, keeping its name, category and description. The body is {"entry": <uuid>, "blocks": [<key>, …]}, as for
 * preset_create. The entries the preset was inserted into stay as they are. See "Editor presets" in
 * docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final readonly class ReplacePresetAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private EditorPresetLookup $lookup,
        private PresetBlocksRequest $blocksRequest,
        private EditorPresetSaverInterface $saver,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request, $uuid): JsonResponse {
            $preset = $this->lookup->editorPreset($uuid);

            try {
                $blocks = $this->blocksRequest->blocks($request->toArray());
            } catch (JsonException|\InvalidArgumentException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            $saved = $this->saver->save(new Preset(
                $preset->name,
                $blocks,
                $preset->category,
                $preset->description,
                $preset->startingPoint,
                uuid: $preset->uuid,
            ));

            return new JsonResponse(['preset' => PresetAnswer::of($saved, $this->translator)]);
        });
    }
}
