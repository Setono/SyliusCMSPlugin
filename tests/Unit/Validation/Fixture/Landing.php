<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A project's content type whose top level holds exactly one Heading, with a constraint on a property that is no field,
 * a required shared field, and fields of its translations
 */
#[ContentType(code: 'landing', label: 'app.landing', prefix: 'landing', blocks: [new Allow(Heading::class, min: 1, max: 1)])]
class Landing extends Entry
{
    #[Assert\NotBlank]
    public ?string $campaign = null;

    /** A Text field, which is translatable by default, is shared here, because it sits on the entry */
    #[Text(label: 'app.landing.subtitle', required: true)]
    #[Assert\Length(max: 10)]
    private ?string $subtitle = null;

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new LandingTranslation();
    }
}
