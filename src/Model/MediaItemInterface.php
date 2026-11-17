<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * One uploaded file in the media library, an image, a video or a document, with its metadata. Content refers to it by
 * its UUID (ADR 0002), and saving it takes effect at once wherever it's used (ADR 0013). Its alt text is translatable,
 * one value per locale. See "What the library holds" and "Metadata" in docs/spec/media.md
 *
 * @method Collection<string, MediaItemTranslationInterface> getTranslations() indexed by locale
 */
interface MediaItemInterface extends ResourceInterface, TranslatableInterface
{
    public function getId(): ?int;

    /**
     * Its identity everywhere: references, transfer, storage paths and URLs
     */
    public function getUuid(): string;

    public function getKind(): MediaKind;

    public function setKind(MediaKind $kind): void;

    /**
     * The folder it sits in, or null for Unsorted
     */
    public function getFolder(): ?MediaFolderInterface;

    public function setFolder(?MediaFolderInterface $folder): void;

    /**
     * What editors call it. It defaults to the original filename without its extension
     */
    public function getName(): string;

    public function setName(string $name): void;

    /**
     * The name of the file that was uploaded, used in the permanent URL
     */
    public function getOriginalFilename(): string;

    public function setOriginalFilename(string $originalFilename): void;

    /**
     * Where its current file sits in the plugin's storage, under its UUID. A replaced file gets a new path
     */
    public function getPath(): string;

    public function setPath(string $path): void;

    /**
     * Read from the file itself
     */
    public function getMimeType(): string;

    public function setMimeType(string $mimeType): void;

    /**
     * The stored file's size in bytes
     */
    public function getSize(): int;

    public function setSize(int $size): void;

    /**
     * In pixels, for images and videos, or null when it couldn't be read
     */
    public function getWidth(): ?int;

    /**
     * In pixels, for images and videos, or null when it couldn't be read
     */
    public function getHeight(): ?int;

    public function setDimensions(?int $width, ?int $height): void;

    /**
     * A free-text credit or copyright line
     */
    public function getCredit(): ?string;

    public function setCredit(?string $credit): void;

    /**
     * The alt text in exactly this locale, or null when it has none there. The fallback chain is
     * Media\AltTextResolverInterface's
     */
    public function getAltText(string $localeCode): ?string;

    /**
     * An empty alt text is none
     */
    public function setAltText(string $localeCode, ?string $altText): void;

    /**
     * Every alt text it has, by locale
     *
     * @return array<string, string>
     */
    public function getAltTexts(): array;

    /**
     * For images: every crop keeps it in view. 0.5 and 0.5 unless an editor moved it
     */
    public function getFocalPoint(): FocalPoint;

    public function setFocalPoint(FocalPoint $focalPoint): void;

    /**
     * For videos: an image media item shown before the video plays
     */
    public function getPoster(): ?self;

    public function setPoster(?self $poster): void;

    /**
     * The SHA-256 of its current file, which finds a file uploaded again
     */
    public function getChecksum(): string;

    public function setChecksum(string $checksum): void;

    /**
     * When it was uploaded, in UTC
     */
    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * When it was last saved: its metadata, its focal point or its file. In UTC
     */
    public function getChangedAt(): \DateTimeImmutable;

    public function setChangedAt(\DateTimeImmutable $changedAt): void;
}
