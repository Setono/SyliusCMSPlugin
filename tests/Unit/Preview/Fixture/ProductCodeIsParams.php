<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture;

use Setono\SyliusCMSPlugin\Field\Text;

final class ProductCodeIsParams
{
    #[Text(label: 'Code')]
    public ?string $code = null;
}
