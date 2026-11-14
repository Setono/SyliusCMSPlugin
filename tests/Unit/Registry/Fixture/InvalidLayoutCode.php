<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

#[Layout(code: 'Invalid-Code', contentType: Page::class, label: 'app.invalid', template: 'invalid.html.twig')]
final class InvalidLayoutCode
{
}
