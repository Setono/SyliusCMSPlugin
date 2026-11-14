<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Textarea;

final class WithTextarea
{
    #[Textarea(label: 'app.excerpt', maxLength: 160)]
    public ?string $excerpt = null;
}
