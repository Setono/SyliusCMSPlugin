<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Field\Text;

final class WithStars
{
    #[Stars(label: 'app.stars')]
    public ?int $stars = null;

    #[Percentage(label: 'app.percentage')]
    public ?int $percentage = null;

    #[Points(label: 'app.points')]
    public ?int $points = null;

    #[Text(label: 'app.text')]
    public ?string $text = null;
}
