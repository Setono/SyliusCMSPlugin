<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;

final class FixedChoiceParams
{
    #[Choice(label: 'Sort by', choices: ['position' => 'Position', 'name' => 'Name'])]
    public string $sort = 'position';
}
