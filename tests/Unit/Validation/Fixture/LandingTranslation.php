<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Symfony\Component\Validator\Constraints as Assert;

class LandingTranslation extends EntryTranslation
{
    #[Assert\Length(max: 10)]
    #[Assert\Regex('/^[A-Z]/')]
    public ?string $teaser = null;
}
