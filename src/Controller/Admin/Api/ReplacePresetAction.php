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
 * preset_create, or {"entry": <uuid>, "startingPoint": true} for a starting point, whose blocks and layout are replaced
 * by an entry's whole tree. A preset stays what it is: a block preset is replaced by blocks, and a starting point by the
 * tree of an entry of its content type. The entries the preset was inserted into, or created from, stay as they are.
 * See "Editor presets" in docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
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
                $content = $this->blocksRequest->content($request->toArray());
            } catch (JsonException|\InvalidArgumentException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            if (null === $preset->startingPoint && null !== $content->startingPoint) {
                return BuilderRequest::badRequest('A block preset is replaced by blocks, not by a whole tree');
            }

            if (null !== $preset->startingPoint && $preset->startingPoint->contentType !== $content->startingPoint?->contentType) {
                return BuilderRequest::badRequest(sprintf('A starting point is replaced by the whole tree of an entry of its content type, "%s"', $preset->startingPoint->contentType));
            }

            $saved = $this->saver->save(new Preset(
                $preset->name,
                $content->blocks,
                $preset->category,
                $preset->description,
                $content->startingPoint,
                uuid: $preset->uuid,
            ));

            return new JsonResponse(['preset' => PresetAnswer::of($saved, $this->translator)]);
        });
    }
}
