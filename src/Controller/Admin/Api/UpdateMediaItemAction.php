<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;
use Setono\SyliusCMSPlugin\Media\MediaItemMetadata;
use Setono\SyliusCMSPlugin\Media\MediaItemSaverInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Saves the metadata in the body, which takes effect at once: any of `name`, `folder` (a folder's UUID, or null for
 * Unsorted, which is Move), `credit`, `altTexts` (the locales that change, an empty or null alt text removing one),
 * `focalPoint` (`{x, y}` from 0 to 1) and `poster` (an image media item's UUID, or null). Answers with the media item.
 * See "Metadata" and "Changes take effect on save" in docs/spec/media.md
 */
final readonly class UpdateMediaItemAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private MediaLookup $lookup,
        private MediaItemSaverInterface $saver,
        private MediaItemAnswer $answer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, function () use ($request, $uuid): JsonResponse {
            $mediaItem = $this->lookup->mediaItem($uuid);

            try {
                $this->saver->save($mediaItem, $this->metadata($mediaItem, $request->toArray()));
            } catch (JsonException|InvalidMediaChangeException $e) {
                return BuilderRequest::badRequest($e->getMessage());
            }

            return new JsonResponse(($this->answer)($mediaItem));
        });
    }

    /**
     * The media item's metadata with the body's changes
     *
     * @param array<array-key, mixed> $body
     *
     * @throws InvalidMediaChangeException
     */
    private function metadata(MediaItemInterface $mediaItem, array $body): MediaItemMetadata
    {
        $metadata = MediaItemMetadata::of($mediaItem);

        return (new MediaItemMetadata(
            array_key_exists('name', $body) ? BodyValue::string($body['name'], 'name') : $metadata->name,
            array_key_exists('folder', $body) ? $this->lookup->folderIn($body['folder'], 'folder') : $metadata->folder,
            array_key_exists('credit', $body) ? BodyValue::nullableString($body['credit'], 'credit') : $metadata->credit,
            $metadata->altTexts,
            array_key_exists('focalPoint', $body) ? self::focalPoint($body['focalPoint']) : $metadata->focalPoint,
            array_key_exists('poster', $body) ? $this->lookup->mediaItemIn($body['poster'], 'poster') : $metadata->poster,
        ))->withAltTexts(array_key_exists('altTexts', $body) ? self::altTexts($body['altTexts']) : []);
    }

    private static function focalPoint(mixed $focalPoint): FocalPoint
    {
        if (!is_array($focalPoint) || !is_numeric($focalPoint['x'] ?? null) || !is_numeric($focalPoint['y'] ?? null)) {
            throw new InvalidMediaChangeException('The focal point must be {x, y}, each a number from 0 to 1');
        }

        try {
            return new FocalPoint((float) $focalPoint['x'], (float) $focalPoint['y']);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidMediaChangeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private static function altTexts(mixed $altTexts): array
    {
        if (!is_array($altTexts)) {
            throw new InvalidMediaChangeException('The alt texts must be an object of locale codes to strings');
        }

        $changed = [];
        foreach ($altTexts as $locale => $altText) {
            $changed[(string) $locale] = BodyValue::nullableString($altText, sprintf('alt text in %s', $locale));
        }

        return $changed;
    }
}
