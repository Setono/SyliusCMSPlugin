<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetDetailsException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetDetails;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * ★ in the builder: saves the selected blocks, or a ⌘-click selection, as a new editor preset, at once. The body is
 * {"entry": <uuid>, "blocks": [<key>, …], "name": …, "category": …, "description": …}: a selection under different
 * parents is saved in tree order, and a block inside another selected block once. The name is required, and the
 * category and description are optional. Answers 201 with the preset. See "Editor presets" in
 * docs/spec/layouts-and-presets.md and "JSON endpoints" in docs/spec/builder.md
 */
final readonly class CreatePresetAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private PresetBlocksRequest $blocksRequest,
        private EditorPresetSaverInterface $saver,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request): JsonResponse {
            try {
                $body = $request->toArray();
                $details = (new PresetDetails(''))->changedBy($body);
                $blocks = $this->blocksRequest->blocks($body);
            } catch (InvalidPresetDetailsException $e) {
                return BuilderRequest::badRequest($e->translatable->trans($this->translator));
            } catch (JsonException|\InvalidArgumentException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            $saved = $this->saver->save($details->applyTo(new Preset('', $blocks)));

            return new JsonResponse(['preset' => PresetAnswer::of($saved, $this->translator)], Response::HTTP_CREATED);
        });
    }
}
