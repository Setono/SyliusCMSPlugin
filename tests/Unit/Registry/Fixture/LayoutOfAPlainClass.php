<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;

/**
 * A layout whose content type is no content type class
 */
#[Layout(code: 'plain', contentType: \stdClass::class, label: 'app.plain', template: 'plain.html.twig')]
final class LayoutOfAPlainClass
{
}
