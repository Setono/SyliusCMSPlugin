<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;

#[AsConditionKind(code: 'text_again', label: 'app.text_again', reads: [], params: WithTextAgain::class)]
final class ConditionKindWithTextAgain
{
}
