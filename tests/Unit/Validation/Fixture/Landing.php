<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A project's content type whose top level holds exactly one Heading, with constraints on a shared field and on a
 * field of its translations
 */
#[ContentType(code: 'landing', label: 'app.landing', prefix: 'landing', blocks: [new Allow(Heading::class, min: 1, max: 1)])]
class Landing extends Entry
{
    #[Assert\NotBlank]
    public ?string $campaign = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new LandingTranslation();
    }
}
