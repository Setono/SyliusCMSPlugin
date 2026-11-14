<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Textarea;

/**
 * A block type with a textarea, a text that its template prints without cms_field(), and a choice
 */
#[BlockType(code: 'quote', label: 'app.quote', template: 'quote.html.twig')]
final class Quote
{
    #[Textarea(label: 'app.quote')]
    public ?string $quote = null;

    #[Text(label: 'app.author')]
    public ?string $author = null;

    #[Choice(label: 'app.size', choices: ['small' => 'app.small', 'large' => 'app.large'])]
    public string $size = 'small';
}
