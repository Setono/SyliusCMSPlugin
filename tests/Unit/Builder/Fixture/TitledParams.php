<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture;

use Setono\SyliusCMSPlugin\Field\Text;

final class TitledParams
{
    #[Text(label: 'Title')]
    public ?string $title = null;
}
