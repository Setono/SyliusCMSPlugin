<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * A project's block type whose template translates its text, and prints the locale of the request and of Sylius's
 * locale context. Only GreetingKernel registers it
 */
#[BlockType(code: 'greeting', label: 'Greeting', template: 'cms/block/greeting.html.twig')]
final class Greeting
{
}
