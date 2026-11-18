<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Date;
use Setono\SyliusCMSPlugin\Field\DateTime;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type with shared fields of several field types, held as Doctrine maps them, and translatable
 * fields on its translations
 */
#[ContentType(code: 'shop', label: 'app.shop', prefix: 'shops')]
class Shop extends Entry
{
    #[Text(label: 'app.city', required: true)]
    private ?string $city = null;

    #[Date(label: 'app.opened_on')]
    private ?\DateTimeImmutable $openedOn = null;

    #[DateTime(label: 'app.renovated_at')]
    private ?\DateTimeImmutable $renovatedAt = null;

    #[Toggle(label: 'app.parking')]
    private bool $parking = false;

    #[Media(label: 'app.photo')]
    protected ?string $photo = null;

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getOpenedOn(): ?\DateTimeImmutable
    {
        return $this->openedOn;
    }

    public function setOpenedOn(?\DateTimeImmutable $openedOn): void
    {
        $this->openedOn = $openedOn;
    }

    public function getRenovatedAt(): ?\DateTimeImmutable
    {
        return $this->renovatedAt;
    }

    public function setRenovatedAt(?\DateTimeImmutable $renovatedAt): void
    {
        $this->renovatedAt = $renovatedAt;
    }

    public function hasParking(): bool
    {
        return $this->parking;
    }

    public function setParking(bool $parking): void
    {
        $this->parking = $parking;
    }

    public function setPhoto(?string $photo): void
    {
        $this->photo = $photo;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new ShopTranslation();
    }
}
