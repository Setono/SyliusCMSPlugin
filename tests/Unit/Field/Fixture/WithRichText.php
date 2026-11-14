<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

final class WithRichText
{
    #[RichText(label: 'app.content')]
    public ?RichTextValue $content = null;
}
