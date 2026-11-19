<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Sylius\Resource\Model\TranslatableTrait;
use Sylius\Resource\Model\TranslationInterface;
use Symfony\Component\Uid\Uuid;

class MediaItem implements MediaItemInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }

    protected ?int $id = null;

    protected string $uuid;

    protected MediaKind $kind = MediaKind::Image;

    protected ?MediaFolderInterface $folder = null;

    protected string $name = '';

    protected string $originalFilename = '';

    protected string $path = '';

    protected string $mimeType = '';

    protected int $size = 0;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?string $credit = null;

    protected float $focalPointX = 0.5;

    protected float $focalPointY = 0.5;

    protected ?MediaItemInterface $poster = null;

    protected string $checksum = '';

    /** In UTC */
    protected \DateTimeImmutable $createdAt;

    /** In UTC */
    protected \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->initializeTranslationsCollection();

        $this->uuid = Uuid::v7()->toRfc4122();
        $this->createdAt = $this->changedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getKind(): MediaKind
    {
        return $this->kind;
    }

    public function setKind(MediaKind $kind): void
    {
        $this->kind = $kind;
    }

    public function getFolder(): ?MediaFolderInterface
    {
        return $this->folder;
    }

    public function setFolder(?MediaFolderInterface $folder): void
    {
        $this->folder = $folder;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setDimensions(?int $width, ?int $height): void
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(?string $credit): void
    {
        $this->credit = $credit;
    }

    public function getAltText(string $localeCode): ?string
    {
        $translation = $this->translations->get($localeCode);

        return $translation instanceof MediaItemTranslationInterface ? $translation->getAltText() : null;
    }

    /**
     * A locale without alt text has no translation row, so the missing-alt filter and search only see real values
     */
    public function setAltText(string $localeCode, ?string $altText): void
    {
        $altText = null === $altText || '' === trim($altText) ? null : $altText;
        $translation = $this->translations->get($localeCode);

        if (null === $altText) {
            if ($translation instanceof TranslationInterface) {
                $this->removeTranslation($translation);
            }

            return;
        }

        if (!$translation instanceof MediaItemTranslationInterface) {
            $translation = $this->createTranslation();
            $translation->setLocale($localeCode);
            $this->addTranslation($translation);
        }

        $translation->setAltText($altText);
    }

    public function getAltTexts(): array
    {
        $altTexts = [];
        foreach ($this->translations as $localeCode => $translation) {
            $altText = $translation instanceof MediaItemTranslationInterface ? $translation->getAltText() : null;
            if (null !== $altText) {
                $altTexts[(string) $localeCode] = $altText;
            }
        }

        ksort($altTexts);

        return $altTexts;
    }

    public function getFocalPoint(): FocalPoint
    {
        return new FocalPoint($this->focalPointX, $this->focalPointY);
    }

    public function setFocalPoint(FocalPoint $focalPoint): void
    {
        $this->focalPointX = $focalPoint->x;
        $this->focalPointY = $focalPoint->y;
    }

    public function getPoster(): ?MediaItemInterface
    {
        return $this->poster;
    }

    public function setPoster(?MediaItemInterface $poster): void
    {
        $this->poster = $poster;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->createdAt);
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->changedAt);
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): void
    {
        $this->changedAt = $changedAt->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @return MediaItemTranslationInterface
     */
    protected function createTranslation(): TranslationInterface
    {
        return new MediaItemTranslation();
    }
}
