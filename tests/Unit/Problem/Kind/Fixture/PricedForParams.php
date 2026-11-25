<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupChoicesProvider;

final class PricedForParams
{
    /** @var string|null a customer group code */
    #[Choice(label: 'app.priced_for.group', choicesProvider: CustomerGroupChoicesProvider::class)]
    public ?string $group = null;
}
