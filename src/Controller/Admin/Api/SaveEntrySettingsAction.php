<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Saves the on-save settings the body holds, outside the draft, and answers with all of them. A setting the body
 * leaves out keeps its value, so the builder sends only what the editor changed
 */
final readonly class SaveEntrySettingsAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private EntrySettingsNormalizer $normalizer,
        private EntrySettingsSaverInterface $saver,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, fn (EntryInterface $entry): JsonResponse => $this->save($request, $entry));
    }

    private function save(Request $request, EntryInterface $entry): JsonResponse
    {
        try {
            $settings = $this->normalizer->denormalize($request->toArray(), EntrySettings::of($entry));
        } catch (JsonException|InvalidEntrySettingsException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $this->saver->save($entry, $settings);

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'settings' => $this->normalizer->normalize(EntrySettings::of($entry)),
        ])), json: true);
    }
}
