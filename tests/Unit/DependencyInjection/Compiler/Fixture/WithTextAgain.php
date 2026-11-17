<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

final class WithTextAgain
{
    #[TextAgain(label: 'app.text')]
    public ?string $text = null;
}
