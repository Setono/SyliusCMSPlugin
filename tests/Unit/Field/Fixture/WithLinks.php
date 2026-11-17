<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;

final class WithLinks
{
    #[Link(label: 'app.link')]
    public ?LinkValue $link = null;

    #[Link(label: 'app.one_for_every_locale', translatable: false)]
    public ?LinkValue $oneForEveryLocale = null;

    #[Link(label: 'app.per_locale', targets: ['url'], translatable: true)]
    public ?LinkValue $perLocale = null;
}
