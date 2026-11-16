<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;

final class LocaleIsParams
{
    /** @var list<string> */
    #[Choice(label: 'Locales', choices: ['en_US' => 'English', 'da_DK' => 'Danish', 'de_DE' => 'German'], multiple: true)]
    public array $locales = [];
}
