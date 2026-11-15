<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The translation of Landing, with constraints on a property that is no field, and translatable fields
 */
class LandingTranslation extends EntryTranslation
{
    #[Assert\Length(max: 10)]
    #[Assert\Regex('/^[A-Z]/')]
    public ?string $teaser = null;

    #[Text(label: 'app.landing.headline', required: true)]
    #[Assert\Length(max: 10)]
    public ?string $headline = null;

    /** A Choice field, which is shared by default, is translatable here, because it sits on the translation */
    #[Choice(label: 'app.landing.tone', choices: ['calm' => 'app.landing.calm', 'loud' => 'app.landing.loud'])]
    public ?string $tone = null;
}
